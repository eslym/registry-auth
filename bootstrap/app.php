<?php

use App\Http\Middleware\AskForTheme;
use App\Http\Middleware\HandleInertiaRequests;
use App\Lib\Utils;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

// Use $_SERVER here because it is passed by the web server
if (isset($_SERVER['ENABLE_X_ACCEL_REDIRECT']) && $_SERVER['ENABLE_X_ACCEL_REDIRECT'] === 'true') {
    Symfony\Component\HttpFoundation\BinaryFileResponse::trustXSendfileTypeHeader();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AskForTheme::class);
        $middleware->web(append: [HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response) {
            if (
                !$response->headers->has('content-type') ||
                !str_starts_with($response->headers->get('content-type'), 'text/html')
            ) {
                return $response;
            }
            if (in_array($response->getStatusCode(), [400, 403, 404, 405, 419, 429, 503])) {
                return Utils::inertiaToResponse(inertia('errors', [
                    'status' => $response->getStatusCode(),
                    'config' => Utils::clientConfigInertia()
                ]), $response->getStatusCode());
            }
            if ($response->getStatusCode() === 500 && !config('app.debug')) {
                return Utils::inertiaToResponse(inertia('errors', [
                    'status' => 500,
                    'config' => Utils::clientConfigInertia()
                ]), 500);
            }
            return $response;
        });
    })->create();
