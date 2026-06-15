<?php

namespace App\Support\Entitlements;

/**
 * Conjunto de entitlements efectivos de un tenant. Value object puro (sin I/O):
 * el cálculo del uso consumido (para `remaining`) se hace afuera —QuotaMiddleware
 * + UsageRecorder, Fase 4— usando el `periodStart` que expone `get()`.
 */
final class EntitlementSet
{
    /** @param array<string, Entitlement> $byFeature */
    public function __construct(private array $byFeature)
    {
    }

    /** ¿El tenant tiene la feature habilitada y vigente? (flags y, en general, gating) */
    public function allows(string $feature): bool
    {
        $entitlement = $this->byFeature[$feature] ?? null;

        return $entitlement !== null && $entitlement->isActive();
    }

    /** Límite de la feature; null = ilimitado o ausente. */
    public function limit(string $feature): ?int
    {
        return ($this->byFeature[$feature] ?? null)?->limit;
    }

    /**
     * Cuánto queda dado el uso ya consumido en el ciclo. null = ilimitado/ausente.
     * `$used` se pasa desde afuera (no toca DB ni cache).
     */
    public function remaining(string $feature, int $used): ?int
    {
        $entitlement = $this->byFeature[$feature] ?? null;

        if ($entitlement === null || $entitlement->limit === null) {
            return null;
        }

        return max(0, $entitlement->limit - $used);
    }

    /** Acceso al entitlement completo (incluye el período del ciclo). */
    public function get(string $feature): ?Entitlement
    {
        return $this->byFeature[$feature] ?? null;
    }
}
