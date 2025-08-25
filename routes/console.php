<?php

use Illuminate\Support\Facades\Artisan;
use \Illuminate\Support\Facades\Schedule;

if(config('jwt-auth.enabled') && ($cron = config('registry.jwt.stable_ca.rotate_leaf'))) {
    Schedule::command('jwt:key', ['--force'])
        ->cron($cron);
}
