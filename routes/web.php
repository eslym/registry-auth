<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\EncryptHistoryMiddleware;

Route::prefix('/')->middleware(['guest'])->group(function () {
    Route::inertia('/login', 'auth/login')
        ->name('auth.login');
    Route::post('/login', [AuthController::class, 'login'])
        ->name('auth.login.submit');
});

Route::prefix('/')->middleware(['auth', EncryptHistoryMiddleware::class])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])
        ->name('auth.logout');

    Route::post('/update-password', [DashboardController::class, 'updatePassword'])
        ->name('dashboard.update-password');

    Route::prefix('/users')->group(function () {
        Route::get('/{user?}', [UserController::class, 'index'])
            ->name('users.index');
        Route::post('/', [UserController::class, 'create'])
            ->name('users.create');
        Route::post('/{user}', [UserController::class, 'update'])
            ->name('users.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])
            ->name('users.destroy');
    });

    Route::prefix('/groups')->group(function () {
        Route::get('/{group?}', [GroupController::class, 'index'])
            ->name('groups.index');
        Route::post('/{group?}', [GroupController::class, 'store'])
            ->name('groups.store');
        Route::delete('/{group}', [GroupController::class, 'destroy'])
            ->name('groups.destroy');
    });
});
