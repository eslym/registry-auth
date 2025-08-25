<?php

namespace App\Console\Commands\JWT;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jwt:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup JWT configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->input->isInteractive() || $this->option('no-interaction')) {
            $this->error('This command must be run in interactive mode.');
            return self::SUCCESS;
        }
        $app = app();
        if ($app->isProduction() && !confirm("You are in production environment. Are you sure you want to continue?")) {
            $this->info('Command cancelled.');
            return self::SUCCESS;
        }
        $env = [];
        $delete = [];
        if ($err = $this->setupPrivateKey($env, $delete)) {
            return $err;
        }
        if (confirm('Do you want to setup a stable CA key/cert pair for long-term validation of issued tokens?', config('registry.jwt.stable_ca.enabled'))) {
            $env['REGISTRY_JWT_CA_ENABLED'] = 'true';
            if ($err = $this->setupPrivateCA($env, $delete)) {
                return $err;
            }
        } else {
            $env['REGISTRY_JWT_CA_ENABLED'] = 'false';
        }
        foreach ($env as $k => $v) {
            $this->info("$k=" . $this->escapeValue($v, false));
        }
        if (confirm('Apply these changes to the .env file?')) {
            $path = $app->environmentFilePath();
            $contents = file_get_contents($path) ?: '';
            foreach ($env as $k => $v) {
                if (preg_match('/^(?:#\s*)?' . preg_quote($k, '/') . '\s*=\s*(?:.*)?$/m', $contents)) {
                    $contents = preg_replace(
                        '/^(?:#\s*)?' . preg_quote($k, '/') . '\s*=\s*(?:.*)?$/m',
                        $k . '=' . $this->escapeValue($v, false),
                        $contents
                    );
                } else {
                    $contents .= "\n" . $k . '=' . $this->escapeValue($v, false);
                }
            }
            foreach ($delete as $k) {
                $contents = preg_replace(
                    '/^(?:#\s*)?' . preg_quote($k, '/') . '\s*=/m',
                    "#$k",
                    $contents
                ) ?: $contents;
            }
            file_put_contents($path, $contents);
            $this->info('Updated .env file at ' . $path);
            $this->info("Please remember to run 'php artisan config:cache' if needed.");
        } else {
            $this->info('Please remember to apply these changes manually to your .env file or set them in your deployment environment.');
        }
        if ($env['REGISTRY_JWT_CA_ENABLED'] === 'true') {
            $this->info("Run 'php artisan jwt:ca' to generate the CA key/cert pair if they do not exist.");
        }
        $this->info("Run 'php artisan jwt:key' to generate the private key/cert pair if they do not exist or need to be rotated.");
        return self::SUCCESS;
    }

    private function setupPrivateKey(array &$env, array &$delete): int
    {
        $options = [
            'RS256' => 'RS256 (RSA with SHA-256, Asymmetric)',
            'RS384' => 'RS384 (RSA with SHA-384, Asymmetric)',
            'RS512' => 'RS512 (RSA with SHA-512, Asymmetric)',
            'ES256' => 'ES256 (ECDSA with SHA-256, Asymmetric)',
            'ES384' => 'ES384 (ECDSA with SHA-384, Asymmetric)',
            'ES512' => 'ES512 (ECDSA with SHA-512, Asymmetric)',
        ];
        $default = config('registry.jwt.algorithm');
        if (!isset($options[$default])) {
            $default = 'RS256';
        }
        $env['REGISTRY_JWT_ALGORITHM'] = select('JWT Algorithm', $options, $default);
        $type = Str::startsWith($env['REGISTRY_JWT_ALGORITHM'], 'RS') ? 'RSA' : 'EC';
        $env['REGISTRY_JWT_KEY_TYPE'] = $type;
        if ($type === 'RSA') {
            $env['REGISTRY_JWT_KEY_SIZE'] = text(
                'RSA Key Size',
                default: (string)config('registry.jwt.key.size'),
                required: true,
                validate: ['size' => ['required', 'integer', 'min:2048', 'max:4096', 'multiple_of:8']],
                hint: "Bit length of the RSA key",
            );
        } else {
            $env['REGISTRY_JWT_KEY_CURVE'] = match ($env['REGISTRY_JWT_ALGORITHM']) {
                'ES384' => 'secp384r1',
                'ES512' => 'secp521r1',
                default => 'prime256v1',
            };
            $this->info("Using curve {$env['REGISTRY_JWT_KEY_CURVE']} for algorithm {$env['REGISTRY_JWT_ALGORITHM']}");
        }
        $this->setPathEnv($env, $delete, 'REGISTRY_JWT_KEY', text(
            'Private Key Path',
            default: config('registry.jwt.key.path'),
            required: true,
            hint: "Path to the private key file",
        ));
        $pass = select('Private Key Passphrase', [
            'no' => 'No passphrase',
            'yes' => 'Yes, prompt for passphrase',
            'secret' => 'Yes, read from secret file',
        ]);
        switch ($pass) {
            case 'no':
                $env['REGISTRY_JWT_KEY_PASS'] = '';
                break;
            case 'yes':
                $env['REGISTRY_JWT_KEY_PASS'] = password(
                    'Private Key Passphrase',
                    required: true,
                    validate: ['pass' => ['required', 'string', 'min:4']],
                    hint: "Passphrase to unlock the private key",
                );
                if ($env['REGISTRY_JWT_KEY_PASS'] !== password(
                        'Confirm Private Key Passphrase',
                        required: true,
                        validate: ['pass' => ['required', 'string', 'min:4']],
                    )) {
                    $this->error('Passphrases do not match.');
                    return self::FAILURE;
                }
                $delete [] = 'REGISTRY_JWT_KEY_PASS_SECRET';
                break;
            case 'secret':
                $env['REGISTRY_JWT_KEY_PASS_SECRET'] = text(
                    'Private Key Passphrase Secret Path',
                    default: config('registry.jwt.key.secret'),
                    required: true,
                    hint: "Path to a file containing the private key passphrase",
                );
                $delete [] = 'REGISTRY_JWT_KEY_PASS';
                break;
        }
        $this->setPathEnv($env, $delete, 'REGISTRY_JWT_CERT', text(
            'Private Key Cert Path',
            default: config('registry.jwt.key.cert'),
            required: true,
            hint: "Path to the certificate file"
        ));
        $env['REGISTRY_JWT_CERT_TTL'] = text(
            'Certificate Validity (days)',
            default: (string)config('registry.jwt.key.ttl', 365),
            required: true,
            validate: ['days' => ['required', 'integer', 'min:1', 'max:3650']],
            hint: "Number of days the certificate is valid",
        );
        return 0;
    }

    private function setupPrivateCA(array &$env, array &$delete): int
    {
        $this->setPathEnv($env, $delete, 'REGISTRY_JWT_CA_KEY', text(
            'CA Private Key Path',
            default: config('registry.jwt.stable_ca.key'),
            required: true,
            hint: "Path to the CA private key file",
        ));
        $pass = select('CA Private Key Passphrase', [
            'no' => 'No passphrase',
            'yes' => 'Yes, prompt for passphrase',
            'secret' => 'Yes, read from secret file',
        ]);
        switch ($pass) {
            case 'no':
                $env['REGISTRY_JWT_CA_KEY_PASS'] = '';
                break;
            case 'yes':
                $env['REGISTRY_JWT_CA_KEY_PASS'] = password(
                    'CA Private Key Passphrase',
                    required: true,
                    validate: ['pass' => ['required', 'string', 'min:4']],
                    hint: "Passphrase to unlock the CA private key",
                );
                if ($env['REGISTRY_JWT_CA_KEY_PASS'] !== password(
                        'Confirm CA Private Key Passphrase',
                        required: true,
                        validate: ['pass' => ['required', 'string', 'min:4']],
                    )) {
                    $this->error('Passphrases do not match.');
                    return self::FAILURE;
                }
                $delete [] = 'REGISTRY_JWT_CA_KEY_PASS_SECRET';
                break;
            case 'secret':
                $env['REGISTRY_JWT_CA_KEY_PASS_SECRET'] = text(
                    'CA Private Key Passphrase Secret Path',
                    default: config('registry.jwt.stable_ca.pass_secret'),
                    required: true,
                    hint: "Path to a file containing the CA private key passphrase",
                );
                $delete [] = 'REGISTRY_JWT_CA_KEY_PASS';
                break;
        }
        $this->setPathEnv($env, $delete, 'REGISTRY_JWT_CA_CERT', text(
            'CA Certificate Path',
            default: config('registry.jwt.stable_ca.cert'),
            required: true,
            hint: "Path to the CA certificate file",
        ));
        $env['REGISTRY_JWT_CA_CERT_TTL'] = text(
            'CA Certificate Validity (days)',
            default: (string)config('registry.jwt.stable_ca.ttl', 3650),
            required: true,
            validate: ['days' => ['required', 'integer', 'min:1', 'max:36500']],
            hint: "Number of days the CA certificate is valid",
        );
        return self::SUCCESS;
    }

    private function setPathEnv(array &$env, array &$delete, string $prefix, string $value): void
    {
        if (Str::startsWith($value, storage_path())) {
            $relativePath = Str::after($value, storage_path() . '/');
            $env["{$prefix}_FILENAME"] = $relativePath;
            $delete [] = "{$prefix}_PATH";
        } else {
            $env["{$prefix}_PATH"] = $value;
            $delete [] = "{$prefix}_FILENAME";
        }
    }

    /**
     * Escapes the value before writing to the contents
     *
     * @param string $value The value
     * @param bool $forceQuote Whether force quoting is preferred
     * @return     string
     *
     * @see https://github.com/MirazMac/DotEnvWriter/blob/796f4fe9508913e0672d58ea192cf8309b0cb478/src/Writer.php#L321
     */
    protected function escapeValue(string $value, bool $forceQuote): string
    {
        if ('' === $value) {
            return '';
        }

        // Quote the values if
        // it contains white-space or the following characters: " \ = : . $ ( )
        // or simply force quote is enabled
        if (preg_match('/\s|"|\\\\|=|:|\.|\$|\(|\)/u', $value) || $forceQuote) {
            // Replace backslashes with even more backslashes so when writing we can have escaped backslashes
            // damn.. that rhymes
            $value = str_replace('\\', '\\\\\\\\', $value);
            // Wrap the
            $value = '"' . addcslashes($value, '"') . '"';
        }

        return $value;
    }
}
