<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->afterResolving(FilesystemManager::class, function (FilesystemManager $manager) {
            $localDisk = $manager->disk('local');
            $localDisk->serveUsing(function ($request, $path, $headers) use ($localDisk) {
                return new BinaryFileResponse($localDisk->path($path), 200, $headers, false);
            });
            $publicDisk = $manager->disk('public');
            $publicDisk->serveUsing(function ($request, $path, $headers) use ($publicDisk) {
                return new BinaryFileResponse($publicDisk->path($path), 200, $headers, true);
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
        Inertia::resolveUrlUsing(fn(Request $request) => $request->fullUrl());
        Password::defaults(fn() => $this->app->isProduction() ?
            Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised(3) :
            Password::min(6)->letters()->numbers()
        );
        RedirectIfAuthenticated::redirectUsing(fn() => route('dashboard'));
        Authenticate::redirectUsing(fn() => route('auth.login'));
        Password::defaults(function () {
            $rule = Password::min(config('password.validation.min', 8));
            if(config('password.validation.max', 8) !== false) {
                $rule = $rule->max(config('password.validation.max', 64));
            }
            if(config('password.validation.mixed_case', true)) {
                $rule = $rule->mixedCase();
            }
            if(config('password.validation.numbers', true)) {
                $rule = $rule->numbers();
            }
            if(config('password.validation.symbols', true)) {
                $rule = $rule->symbols();
            }
            if(config('password.validation.uncompromised',  $this->app->isProduction())) {
                $rule = $rule->uncompromised(config('password.validation.threshold', 3));
            }
            return $rule;
        });
        EncryptCookies::except(['tz', 'theme']);
    }
}
