<?php

namespace App\Http\Middleware;

use App\Http\Resources\CurrentUserResource;
use App\Lib\Utils;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        if (file_exists($manifest = public_path('build/manifest.json'))) {
            return hash_file('xxh128', $manifest);
        }
        return null;
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            'config' => Utils::clientConfigInertia(),
            'user' => fn() => $request->user() ? CurrentUserResource::make($request->user()) : null,
            'route' => fn() => $request->route()->getName() ?? "",
            '_toast' => fn() => $request->session()->get('toast'),
            '_alert' => fn() => $request->session()->get('alert'),
            ...parent::share($request)
        ];
    }

    public function resolveValidationErrors(Request $request)
    {
        if (!$request->hasSession() || !$request->session()->has('errors')) {
            return (object)[];
        }

        return (object)collect($request->session()->get('errors')->getBags())->map(function ($bag) {
            return (object)collect($bag->messages())->toArray();
        })->pipe(function ($bags) use ($request) {
            if ($bags->has('default') && $request->header(Header::ERROR_BAG)) {
                return [$request->header(Header::ERROR_BAG) => $bags->get('default')];
            }

            if ($bags->has('default')) {
                return $bags->get('default');
            }

            return $bags->toArray();
        });
    }
}
