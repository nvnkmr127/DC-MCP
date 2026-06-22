<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof Response) {
            // Content Security Policy
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
                   "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com; " .
                   "connect-src 'self' https:; " .
                   "frame-ancestors 'none'; " .
                   "object-src 'none';";
            $response->headers->set('Content-Security-Policy', $csp);

            // HTTP Strict Transport Security
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

            // X-Frame-Options (Clickjacking protection)
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

            // X-Content-Type-Options (MIME-sniffing protection)
            $response->headers->set('X-Content-Type-Options', 'nosniff');

            // Referrer-Policy
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

            // Permissions-Policy
            $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        }

        return $response;
    }
}
