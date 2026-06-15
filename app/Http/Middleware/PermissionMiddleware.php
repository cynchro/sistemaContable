<?php

namespace App\Http\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Support\Auth\PermissionChecker;
use App\Exceptions\AuthException;
use App\Exceptions\ForbiddenException;
use App\Support\Contracts\MiddlewareInterface;

/**
 * Autoriza una ruta exigiendo que el rol del usuario tenga un permiso concreto
 * con al menos el nivel de acceso requerido (read = 1, write = 2).
 *
 * Uso en rutas:
 *   PermissionMiddleware::class . ':facturas'          → exige lectura
 *   PermissionMiddleware::class . ':facturas:write'    → exige escritura
 */
class PermissionMiddleware implements MiddlewareInterface
{
    private const LEVELS = [
        'read'  => PermissionChecker::LEVEL_READ,
        'write' => PermissionChecker::LEVEL_WRITE,
    ];

    public function __construct(
        private PermissionChecker $checker,
        private string $permission = '',
        private string $level = 'read'
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->user();

        if (!$user) {
            throw new AuthException('Unauthenticated.');
        }

        $rolId    = (int) ($user['rol'] ?? 0);
        $required = self::LEVELS[$this->level] ?? PermissionChecker::LEVEL_READ;

        if (!$this->checker->allows($rolId, $this->permission, $required)) {
            throw new ForbiddenException(
                "Permission [{$this->permission}:{$this->level}] required."
            );
        }

        return $next($request);
    }
}
