<?php

use App\Http\Controllers\TokenController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/token', [TokenController::class, 'issue'])
    ->name('api.token');

Route::post('/token', [TokenController::class, 'byRefreshToken'])
    ->name('api.refresh_token');

Route::post('/registry-events', [WebhookController::class, 'events'])
    ->name('api.registry.webhook');
