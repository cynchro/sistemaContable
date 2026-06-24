<?php

namespace App\Http\Middleware;

use DateTimeImmutable;
use App\Support\Request;
use App\Support\Response;
use App\Exceptions\AuthException;
use App\Exceptions\QuotaExceededException;
use App\Exceptions\PaymentRequiredException;
use App\Support\Contracts\MiddlewareInterface;
use App\Support\Contracts\UsageRecorderInterface;
use App\Support\Contracts\EntitlementResolverInterface;

/**
 * Exige que al tenant le quede cuota de una feature en el ciclo vigente.
 *
 * Uso en rutas (patrón parametrizado del router):
 *   'App\Http\Middleware\QuotaMiddleware:api.calls'
 *
 * Debe correr después de TenantMiddleware. El `used` se calcula contando
 * usage_events desde el `periodStart` del entitlement (o el inicio del mes
 * calendario si no hay billing). NO registra uso: eso lo hace el código de
 * negocio vía UsageRecorderInterface::record() (el costo por request varía).
 *
 *   - sin el entitlement / deshabilitado → 402 (PaymentRequired)
 *   - limit null (ilimitado)             → pasa
 *   - cuota agotada                      → 429 + Retry-After (hasta el reset)
 */
final class QuotaMiddleware implements MiddlewareInterface
{
    public function __construct(
        private EntitlementResolverInterface $resolver,
        private UsageRecorderInterface $usage,
        private string $feature = ''
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $tenantId = $request->tenantId();

        if ($tenantId === null) {
            throw new AuthException('Tenant context missing.');
        }

        if ($this->feature === '') {
            return $next($request);
        }

        $set         = $this->resolver->for($tenantId);
        $entitlement = $set->get($this->feature);

        if ($entitlement === null || !$entitlement->isActive()) {
            throw new PaymentRequiredException("Feature [{$this->feature}] is not enabled for this tenant.");
        }

        // Ilimitado: nada que contar.
        if ($entitlement->limit === null) {
            return $next($request);
        }

        $since     = $entitlement->periodStart ?? $this->calendarMonthStart();
        $used      = $this->usage->total($tenantId, $this->feature, $since);
        $remaining = $set->remaining($this->feature, $used);

        if ($remaining !== null && $remaining <= 0) {
            throw new QuotaExceededException(
                "Quota for [{$this->feature}] exhausted.",
                $this->retryAfter($entitlement->periodEnd)
            );
        }

        return $next($request);
    }

    private function retryAfter(?DateTimeImmutable $periodEnd): ?int
    {
        if ($periodEnd === null) {
            return null;
        }

        $seconds = $periodEnd->getTimestamp() - time();

        return $seconds > 0 ? $seconds : null;
    }

    private function calendarMonthStart(): DateTimeImmutable
    {
        return new DateTimeImmutable('first day of this month midnight');
    }
}
