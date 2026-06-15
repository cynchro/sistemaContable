<?php

namespace App\Http\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Exceptions\AuthException;
use App\Exceptions\ForbiddenException;
use App\Support\Contracts\MiddlewareInterface;

/**
 * Exige que el Principal autenticado posea un scope concreto.
 *
 * Uso en rutas (patrón parametrizado del router):
 *   'App\Http\Middleware\ScopeMiddleware:clientes.read'
 *
 * Es ortogonal a PermissionMiddleware (RBAC de usuario) y a los entitlements
 * del tenant: el scope acota qué puede tocar una credencial dada.
 */
final class ScopeMiddleware implements MiddlewareInterface
{
    public function __construct(private string $scope = '')
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $principal = $request->principal();

        if ($principal === null) {
            throw new AuthException('Unauthenticated.');
        }

        if ($this->scope !== '' && !$principal->hasScope($this->scope)) {
            throw new ForbiddenException("Scope [{$this->scope}] required.");
        }

        return $next($request);
    }
}
