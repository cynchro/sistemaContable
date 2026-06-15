<?php

namespace App\Http\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Support\Roles;
use App\Support\Auth\PermissionChecker;
use App\Support\Contracts\MiddlewareInterface;
use App\Exceptions\ForbiddenException;

class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(private PermissionChecker $checker)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->user();

        if (!$user || !$this->checker->allows((int) ($user['rol'] ?? 0), Roles::SUPER_PERMISSION)) {
            throw new ForbiddenException('Admin access required.');
        }

        return $next($request);
    }
}
