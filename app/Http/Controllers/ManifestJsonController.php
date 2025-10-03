<?php

namespace App\Http\Controllers;

class ManifestJsonController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'name' => config('app.name'),
            'icons' => [
                [
                    'src' => url('/favicon.svg'),
                    'sizes' => 'any',
                    'type' => 'image/svg+xml',
                ], [
                    'src' => url('/favicon-512w.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ]
            ],
            'start_url' => '/',
            'display' => 'standalone',
            'theme_color' => '#817be9',
            'background_color' => '#fcfcff',
        ]);
    }
}
