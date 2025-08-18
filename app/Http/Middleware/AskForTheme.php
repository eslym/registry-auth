<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AskForTheme
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['GET', 'HEAD'])) {
            return $next($request);
        }
        $response = $next($request);

        $type = $response->headers->get('Content-Type');

        if (!$type || !str_starts_with($type, 'text/html')) {
            return $response;
        }

        $response->headers->set('Accept-CH', 'Sec-CH-Prefers-Color-Scheme');

        if ($response->headers->has('Vary')) {
            $varies = array_map('trim', explode(',', $response->headers->get('Vary')));
            if (!in_array('Sec-CH-Prefers-Color-Scheme', $varies)) {
                $varies[] = 'Sec-CH-Prefers-Color-Scheme';
                $response->headers->set('Vary', implode(', ', $varies));
            }
        } else {
            $response->headers->set('Vary', 'Sec-CH-Prefers-Color-Scheme');
        }

        return $response;
    }
}
