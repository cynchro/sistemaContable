<?php

namespace App\Support\Entitlements;

use DateTimeImmutable;

/**
 * Un derecho efectivo de un tenant sobre una feature. Tres tipos:
 *   - 'flag'  → tiene / no tiene (limit irrelevante)
 *   - 'quota' → límite numérico por ciclo (limit + period_start/period_end)
 *   - 'seat'  → límite de asientos (limit)
 *
 * `limit` null = ilimitado. Value object inmutable, sin I/O.
 */
final class Entitlement
{
    public function __construct(
        public readonly string $feature,
        public readonly string $type,
        public readonly ?int $limit = null,
        public readonly bool $enabled = true,
        public readonly ?DateTimeImmutable $periodStart = null,
        public readonly ?DateTimeImmutable $periodEnd = null,
        public readonly ?DateTimeImmutable $expiresAt = null
    ) {
    }

    public function isActive(?DateTimeImmutable $now = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $now ??= new DateTimeImmutable();

        return $this->expiresAt === null || $this->expiresAt >= $now;
    }
}
