<?php

namespace App\Lib;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\OptionalProp;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

final class Utils
{
    public static function inertiaToResponse(\Inertia\Response $responsable, $status = 200): Response
    {
        $request = request();
        $response = $responsable->toResponse($request);
        $response->setStatusCode($status);
        return $response;
    }

    public static function isDarkTheme(): bool
    {
        $request = request();
        if ($request->cookies->has('theme')) {
            return $request->cookies->get('theme') === 'dark';
        }
        if ($request->headers->has('Sec-CH-Prefers-Color-Scheme')) {
            return $request->headers->get('Sec-CH-Prefers-Color-Scheme') === 'dark';
        }
        return false;
    }

    public static function clientConfig(): array
    {
        $tz = request()->cookie('tz', config('app.timezone'));
        return [
            'appName' => config('app.name'),
            'timezone' => $tz,
        ];
    }

    public static function clientConfigInertia(): Closure|OptionalProp
    {
        return request()->header(Header::INERTIA) ?
            Inertia::optional(fn() => Utils::clientConfig()) :
            fn() => Utils::clientConfig();
    }
}
