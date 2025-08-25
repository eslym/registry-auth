<?php

namespace App\Console\Commands\JWT;

use App\Lib\Crypto\CertificateService as Certs;
use Illuminate\Console\Command;
use function Laravel\Prompts\confirm;

class GenerateKeyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jwt:key {--force : Overwrite existing key/cert files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new private key and certificate for signing JWTs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $app = app();
        $force = $this->option('force');
        if(!$force) {
            if($app->isProduction() && !confirm('You are in production mode. Are you sure you want to generate a new key and certificate? This may overwrite existing files.', false)) {
                $this->info('Aborted.');
                return self::FAILURE;
            }
            $exists = file_exists(Certs::leafKeyPath()) || file_exists(Certs::leafCertPath());
            if($exists && !confirm('Key or certificate file already exists. Do you want to overwrite them?', false)) {
                $this->info('Aborted.');
                return self::FAILURE;
            }
        }

        [$leafKey, $leafKeyPem] = Certs::generatePrivateKey();
        Certs::writeAtomic(Certs::leafKeyPath(), $leafKeyPem);
        $leafCsr = Certs::createCsr($leafKey, ['CN' => config('registry.jwt.issuer')]);
        $leafCrt = Certs::signLeafWithFallback($leafCsr, $leafKey);

        Certs::writeAtomic(Certs::leafCertPath(), $leafCrt, 0644);

        $this->info('Generated key and certificate:');
        $this->line(' - ' . Certs::leafKeyPath());
        $this->line(' - ' . Certs::leafCertPath());

        return self::SUCCESS;
    }
}
