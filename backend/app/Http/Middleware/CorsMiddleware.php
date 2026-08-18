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
        $response = $request->method() === 'OPTIONS'
            ? (new Response())->withStatus(204)
            : $next($request);

        foreach (self::headersFor($request->header('Origin') ?? '') as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * Cabeceras CORS para un Origin dado — extraído como estático para que
     * `App\Exceptions\Handler` (el `set_exception_handler` global) también pueda
     * aplicarlas. Ese handler corre FUERA del pipeline de middlewares (atrapa
     * excepciones que escapan de `Kernel::handle()`), así que sin esto ninguna
     * respuesta de error (403/404/409/422...) llevaba CORS: en cross-origin el
     * navegador la bloqueaba entera y el frontend solo veía un mensaje genérico
     * de fallback en vez del mensaje real del backend.
     *
     * @return array<string, string>
     */
    public static function headersFor(string $origin): array
    {
        $origins     = Config::get('cors.allowed_origins', ['*']);
        $credentials = Config::get('cors.allow_credentials', false);
        $headers     = [];

        // Wildcard + credentials is forbidden by the CORS spec; browsers reject it.
        // When credentials are required, always reflect the specific allowed origin.
        if (in_array('*', $origins, true) && !$credentials) {
            $headers['Access-Control-Allow-Origin'] = '*';
        } elseif ($origin !== '' && in_array($origin, $origins, true)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Vary']                        = 'Origin';
        }

        $headers['Access-Control-Allow-Methods'] = implode(', ', Config::get('cors.allowed_methods', []));
        $headers['Access-Control-Allow-Headers'] = implode(', ', Config::get('cors.allowed_headers', []));

        if ($credentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $headers;
    }
}
