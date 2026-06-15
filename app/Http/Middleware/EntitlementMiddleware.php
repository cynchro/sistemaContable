<?php

namespace App\Http\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Exceptions\AuthException;
use App\Exceptions\PaymentRequiredException;
use App\Support\Contracts\MiddlewareInterface;
use App\Support\Contracts\EntitlementResolverInterface;

/**
 * Exige que el tenant tenga habilitada una feature.
 *
 * Uso en rutas (patrón parametrizado del router):
 *   'App\Http\Middleware\EntitlementMiddleware:ia.rag'
 *
 * Debe correr después de TenantMiddleware (necesita el tenant resuelto).
 * Falla con 402 (Payment Required) — señal de "actualizá tu plan", distinta de
 * 403 (scope/permiso). Ortogonal a ScopeMiddleware y PermissionMiddleware.
 */
final class EntitlementMiddleware implements MiddlewareInterface
{
    public function __construct(
        private EntitlementResolverInterface $resolver,
        private string $feature = ''
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $tenantId = $request->tenantId();

        if ($tenantId === null) {
            throw new AuthException('Tenant context missing.');
        }

        if ($this->feature !== '' && !$this->resolver->for($tenantId)->allows($this->feature)) {
            throw new PaymentRequiredException("Feature [{$this->feature}] is not enabled for this tenant.");
        }

        return $next($request);
    }
}
