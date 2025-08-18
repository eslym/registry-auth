<?php

use App\Http\Controllers\TokenController;
use Illuminate\Support\Facades\Route;

Route::get('/token', [TokenController::class, 'issue'])
    ->name('api.token');
