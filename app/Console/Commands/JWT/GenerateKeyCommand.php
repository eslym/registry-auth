<?php

namespace App\Console\Commands\JWT;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class GenerateKeyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jwt:generate
     {--t|type= : The key type to generate (rsa, ec)}
     {--s|size= : The key size (bits for RSA, curve size for EC)}
     {--P|private= : The private key file path}
     {--U|public= : The public key file path}
     {--c|cert= : The certificate file path}
     {--p|pass= : The passphrase for the private key}
     {--d|dry-run : Perform a dry run without writing files}
     {--E|write-env : Write the key and certificate paths to the .env file}
     {--f|force : Force overwrite existing key and certificate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate new JWT key and certificate';

    protected ?string $type = null;
    protected ?string $private = null;
    protected ?string $public = null;
    protected ?int $size = null;
    protected ?string $cert = null;
    protected ?string $pass = null;
    protected bool $dryRun = false;
    protected bool $writeEnv = false;
    protected bool $force = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->type = strtolower($this->option('type') ?? 'rsa');
        $this->size = $this->option('size');
        $this->private = $this->option('private') ?: config('registry.key.path', storage_path('registry.pem'));
        $this->public = $this->option('public') ?: "{$this->private}.pub";
        $this->cert = $this->option('cert') ?: config('registry.key.cert', storage_path('registry.crt'));
        $this->pass = $this->option('pass');
        $this->dryRun = (bool)$this->option('dry-run');
        $this->writeEnv = (bool)$this->option('write-env');
        $this->force = (bool)$this->option('force');
        $interactive = $this->input->isInteractive() && !$this->option('no-interaction');

        if ($interactive) {
            if ($this->handleInteractive()) return 1;
        }

        if (!in_array($this->type, ['rsa', 'ec'])) {
            $this->error('Invalid key type. Supported types: rsa, ec');
            return 1;
        }

        $this->size = $this->size ?: ($this->type === 'rsa' ? 2048 : 384);
        if (!filter_var($this->size, FILTER_VALIDATE_INT)) {
            $this->error('Invalid size value.');
            return 1;
        }
        $this->size = (int)$this->size;

        if ($this->type === 'rsa') {
            if ($this->size < 2048 || $this->size % 8 !== 0) {
                $this->error('RSA key size must be >= 2048 and multiple of 8.');
                return 1;
            }
        } else {
            if (!in_array($this->size, [256, 384, 521])) {
                $this->error('EC key size must be 256, 384 or 521.');
                return 1;
            }
        }

        $opts = [
            'private_key_bits' => $this->type === 'rsa' ? $this->size : null,
            'private_key_type' => $this->type === 'rsa' ? OPENSSL_KEYTYPE_RSA : OPENSSL_KEYTYPE_EC,
        ];
        if ($this->type === 'ec') {
            $opts['curve_name'] = match ($this->size) {
                256 => 'prime256v1',
                384 => 'secp384r1',
                521 => 'secp521r1',
            };
        } else {
            $opts['digest_alg'] = 'sha256';
        }

        $key = openssl_pkey_new(array_filter($opts));
        if (!$key) {
            $this->error('Failed to generate key');
            return 1;
        }

        if ($this->checkFile($this->private, $interactive, 'Private key')) return 1;
        if (!$this->dryRun && !$this->writeFile($this->private, fn() => openssl_pkey_export($key, $pem, $this->pass ?: null) ? $pem : false, 'private key')) return 1;
        if ($this->dryRun) $this->info("Dry run: Private key would be written to {$this->private}");

        $details = openssl_pkey_get_details($key);
        if (!$details || !isset($details['key'])) {
            $this->error('Failed to get public key');
            return 1;
        }

        if ($this->checkFile($this->public, $interactive, 'Public key')) return 1;
        if (!$this->dryRun && !$this->writeFile($this->public, fn() => $details['key'], 'public key')) return 1;
        if ($this->dryRun) $this->info("Dry run: Public key would be written to {$this->public}");

        $csrDigest = match (true) {
            $this->type === 'ec' && $this->size === 384 => 'sha384',
            $this->type === 'ec' && $this->size === 521 => 'sha512',
            default => 'sha256',
        };

        $dn = ['commonName' => config('registry.issuer')];
        $csr = openssl_csr_new($dn, $key, ['digest_alg' => $csrDigest]);
        if (!$csr) {
            $this->error('Failed to create CSR');
            return 1;
        }
        $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => $csrDigest]);
        if (!$cert) {
            $this->error('Failed to sign CSR');
            return 1;
        }

        if ($this->checkFile($this->cert, $interactive, 'Certificate')) return 1;
        if (!$this->dryRun && !$this->writeFile($this->cert, function () use ($cert) {
                if (!openssl_x509_export($cert, $out)) {
                    return false;
                }
                return $out;
            }, 'certificate')) return 1;
        if ($this->dryRun) $this->info("Dry run: Certificate would be written to {$this->cert}");

        if ($this->writeEnv || ($interactive && confirm('Update .env file?'))) {
            $this->updateEnv();
        }
        $this->info('JWT key and certificate generation completed.');
        return 0;
    }

    private function checkFile($path, $interactive, $label): bool
    {
        if (file_exists($path)) {
            if (!$interactive && !$this->force) {
                $this->error("$label file exists. Use --force to overwrite.");
                return true;
            }
            if ($interactive && !confirm("$label file exists. Overwrite?")) {
                $this->error('Operation cancelled.');
                return true;
            }
        } else {
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, recursive: true) && !is_dir($dir)) {
                $this->error("Failed to create directory: $dir");
                return true;
            }
        }
        return false;
    }

    private function writeFile($path, $contentCallback, $label): bool
    {
        $content = $contentCallback();
        if ($content === false) {
            $this->error("Failed to export $label.");
            return false;
        }
        if (file_put_contents($path, $content) === false) {
            $this->error("Failed to write $label to $path");
            return false;
        }
        $this->info("$label written to $path");
        return true;
    }

    private function updateEnv(): void
    {
        $envPath = app()->environmentFilePath();
        if (!is_writable($envPath)) {
            $this->error("Cannot write to .env file: {$envPath}");
            return;
        }
        $existing = Dotenv::createArrayBacked(app()->environmentPath(), app()->environmentFile())->load();
        $envContent = file_get_contents($envPath);
        $replacements = [
            'REGISTRY_JWT_KEY_PASS' => $this->pass ? $this->quoteEnv($this->pass) : '',
        ];
        if(isset($existing['REGISTRY_JWT_KEY_PATH']) && Str::startsWith($this->private, storage_path())) {
            $replacements['REGISTRY_JWT_KEY_FILENAME'] = $this->quoteEnv(Str::after($this->private, storage_path()));
        } else {
            $replacements['REGISTRY_JWT_KEY_PATH'] = $this->quoteEnv($this->private);
        }
        if(isset($existing['REGISTRY_JWT_CERT_PATH']) && Str::startsWith($this->cert, storage_path())) {
            $replacements['REGISTRY_JWT_CERT_FILENAME'] = $this->quoteEnv(Str::after($this->cert, storage_path()));
        } else {
            $replacements['REGISTRY_JWT_CERT_PATH'] = $this->quoteEnv($this->cert);
        }
        foreach ($replacements as $key => $value) {
            $pattern = "/^{$key}=(.*)$/m";
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
            } else if ($value) {
                $envContent .= "\n{$key}={$value}";
            }
        }
        if ($this->dryRun) {
            $this->info('Dry run: Would update .env file');
            $this->line($envContent);
        } else {
            if (file_put_contents($envPath, Str::finish($envContent, "\n")) === false) {
                $this->error('Failed to write to .env file');
                return;
            }
            $this->info('.env file updated');
        }
    }

    public function handleInteractive(): int
    {
        if (!$this->option('type')) {
            $this->type = select('Select key type', ['rsa' => 'RSA', 'ec' => 'EC'], 'rsa');
        }
        if (!$this->option('size')) {
            $this->size = $this->type === 'rsa'
                ? (int)select('Select key size', [2048 => '2048', 3072 => '3072', 4096 => '4096'], 2048)
                : (int)select('Select curve size', [256 => '256', 384 => '384', 521 => '521'], 384);
        }
        if (!$this->option('private')) {
            $this->private = text('Enter private key file path', '', $this->private, true);
        }
        if (!$this->option('public')) {
            $this->public = text('Enter public key file path', '', $this->public, true);
        }
        if (!$this->option('cert')) {
            $this->cert = text('Enter certificate file path', '', $this->cert, true);
        }
        if (!$this->option('pass')) {
            $pass1 = password('Enter passphrase (leave empty for none)');
            $pass2 = $pass1 ? password('Confirm passphrase', required: true) : '';
            if ($pass1 && $pass1 !== $pass2) {
                $this->error('Passphrase does not match.');
                return 1;
            }
            $this->pass = $pass1 ?: null;
        }
        return 0;
    }

    private function quoteEnv(string $value): string
    {
        // Quote the value for .env file
        $value = str_replace("\\", '\\\\', $value); // Escape backslashes
        if (Str::contains($value, [' ', '"', "'", '=', "\n", "\r"])) {
            // Escape quotes and newlines
            $value = str_replace(['"', "'", "\n", "\r"], ['\"', "\'", '\n', '\r'], $value);
            // Wrap in double quotes
            $value = '"' . $value . '"';
        }
        return $value;
    }
}
