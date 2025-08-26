<?php

namespace App\Console\Commands;

use App\Models\AccessControl;
use App\Models\AccessToken;
use App\Models\Group;
use App\Models\User;
use Illuminate\Console\Command;

class CleanAccessControlsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-acls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up unused access control entries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        AccessControl::whereDoesntHaveMorph('owner', [User::class, Group::class, AccessToken::class])
            ->chunkById(100, function ($acls) {
                $count = $acls->count();
                $this->info("Deleting {$count} orphaned access control entries...");
                AccessControl::whereIn('id', $acls->pluck('id'))->delete();
            });
    }
}
