<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProfileController;
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

    Route::prefix('/profile')->group(function () {
        Route::get('/{token?}', [ProfileController::class, 'index'])
            ->name('profile.index');
        Route::post('/', [ProfileController::class, 'createToken'])
            ->name('profile.createToken');
        Route::delete('/{token}', [ProfileController::class, 'revokeToken'])
            ->name('profile.revokeToken');
    });

    Route::post('/update-password', [ProfileController::class, 'updatePassword'])
        ->name('profile.update-password');

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
