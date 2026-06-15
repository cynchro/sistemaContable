<?php

namespace App\Http\Middleware;

use App\Support\Config;
use App\Support\Request;
use App\Support\Response;
use App\Support\Contracts\MiddlewareInterface;
use App\Exceptions\ValidationException;

class RequestSizeLimitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $maxBytes      = Config::get('app.max_request_size', 2 * 1024 * 1024); // 2 MB default
        $contentLength = (int) ($request->header('Content-Length') ?? 0);

        if ($contentLength > $maxBytes) {
            throw new ValidationException(
                ['body' => ['Request body exceeds the maximum allowed size of ' . ($maxBytes / 1024 / 1024) . ' MB.']]
            );
        }

        return $next($request);
    }
}
