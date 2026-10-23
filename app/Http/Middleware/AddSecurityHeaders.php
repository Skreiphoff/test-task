<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        // The debug error page needs inline scripts and styles, so the policy is only enforced with debug off.
        // 'unsafe-eval' is required by the standard Alpine.js build (it evaluates x-data / @event expressions).
        if (! config('app.debug')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self'; img-src 'self' data:; "
                ."frame-ancestors 'none'; base-uri 'none'; form-action 'self'",
            );
        }

        return $response;
    }
}
