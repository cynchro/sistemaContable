<?php

namespace App\Http\Middleware;

use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\Contracts\MiddlewareInterface;

class RequestLoggerMiddleware implements MiddlewareInterface
{
    public function __construct(private Logger $logger)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $start    = microtime(true);
        $response = $next($request);
        $ms       = round((microtime(true) - $start) * 1000, 2);

        $this->logger->info('HTTP Request', [
            'method'      => $request->method(),
            'uri'         => $request->uri(),
            'ip'          => $request->ip(),
            'status'      => $response->getStatus(),
            'duration_ms' => $ms,
        ]);

        return $response;
    }
}
