<?php

namespace App\Http\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Support\Contracts\MiddlewareInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        foreach (self::headers() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * Estático para que `App\Exceptions\Handler` (el `set_exception_handler` global,
     * que corre fuera del pipeline de middlewares) también pueda aplicarlas —
     * ver el docblock equivalente en `CorsMiddleware::headersFor()`.
     *
     * @return array<string, string>
     */
    public static function headers(): array
    {
        $csp = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';"
            . " img-src 'self' data:; font-src 'self'";

        return [
            'X-Content-Type-Options'    => 'nosniff',
            'X-Frame-Options'           => 'DENY',
            'Referrer-Policy'           => 'strict-origin-when-cross-origin',
            'Content-Security-Policy'   => $csp,
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Permissions-Policy'        => 'geolocation=(), microphone=(), camera=()',
        ];
    }
}
