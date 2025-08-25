<?php

namespace App\Console\Commands\JWT;

use App\Lib\Registry\Grant;
use App\Lib\Registry\Token;
use Illuminate\Console\Command;

class IssueTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jwt:issue {subject} {scopes?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Issue a new JWT for the given subject and optional scopes for testing purposes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $subject = $this->argument('subject');
        $scopes = $this->argument('scopes') ? explode(',', $this->argument('scopes')) : [];

        $access = array_map(fn($scope)=>Grant::parse(trim($scope)), $scopes);
        $token = Token::issue($subject, $access);
        $this->line($token['token']);

        return self::SUCCESS;
    }
}
