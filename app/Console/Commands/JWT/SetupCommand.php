<?php

namespace App\Console\Commands\JWT;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
    protected $description = 'Setup JWT configuration and generate keys if needed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->input->isInteractive() || $this->option('no-interaction')) {
            $this->error('This command must be run in interactive mode.');
            return 1;
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
