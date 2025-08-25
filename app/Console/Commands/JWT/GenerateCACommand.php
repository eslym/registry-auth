<?php

namespace App\Console\Commands\JWT;

use App\Lib\Crypto\CertificateService as Certs;
use Illuminate\Console\Command;
use function Laravel\Prompts\confirm;

class GenerateCACommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jwt:ca {--force : Overwrite existing CA key/cert files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a stable CA key and self-signed certificate for signing JWTs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $app = app();
        $force = $this->option('force');
        if(!$force) {
            if($app->isProduction() && !confirm('You are in production mode. Are you sure you want to generate a new CA key and certificate? This may overwrite existing files.', false)) {
                $this->info('Aborted.');
                return self::FAILURE;
            }
            if(!config('registry.jwt.stable_ca.enabled') && !confirm('The stable CA feature is disabled in config. Do you want to continue?', false)) {
                $this->info('Aborted.');
                return self::FAILURE;
            }
            $exists = file_exists(Certs::caKeyPath()) || file_exists(Certs::caCertPath());
            if($exists && !confirm('CA key or certificate file already exists. Do you want to overwrite them?', false)) {
                $this->info('Aborted.');
                return self::FAILURE;
            }
        }

        [$caKey, $caKeyPem] = Certs::generateCAPrivateKey();
        Certs::writeAtomic(Certs::caKeyPath(), $caKeyPem);
        $caCsr = Certs::createCsr($caKey, ['CN' => config('registry.jwt.issuer')]);
        $caCrt = Certs::selfSignCA($caCsr, $caKey);
        Certs::writeAtomic(Certs::caCertPath(), $caCrt, 0644);

        $this->info('Generated CA key and self-signed certificate:');
        $this->line(' - ' . Certs::caKeyPath());
        $this->line(' - ' . Certs::caCertPath());

        return self::SUCCESS;
    }
}
