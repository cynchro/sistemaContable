<?php

namespace App\Http\Middleware;

use App\Support\Config;
use App\Support\Request;
use App\Support\Response;
use App\Support\Contracts\MiddlewareInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $origins     = Config::get('cors.allowed_origins', ['*']);
        $credentials = Config::get('cors.allow_credentials', false);
        $origin      = $request->header('Origin') ?? '';

        $response = $request->method() === 'OPTIONS'
            ? (new Response())->withStatus(204)
            : $next($request);

        // Wildcard + credentials is forbidden by the CORS spec; browsers reject it.
        // When credentials are required, always reflect the specific allowed origin.
        if (in_array('*', $origins, true) && !$credentials) {
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        } elseif ($origin !== '' && in_array($origin, $origins, true)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Vary', 'Origin');
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Methods', implode(', ', Config::get('cors.allowed_methods', [])))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', Config::get('cors.allowed_headers', [])));

        if ($credentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
