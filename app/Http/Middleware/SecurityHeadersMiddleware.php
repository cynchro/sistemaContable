<?php

namespace App\Http\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Support\Contracts\MiddlewareInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $csp = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';"
            . " img-src 'self' data:; font-src 'self'";

        return $next($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Content-Security-Policy', $csp)
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
    }
}
