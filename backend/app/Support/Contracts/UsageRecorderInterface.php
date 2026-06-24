<?php

namespace App\Support\Contracts;

use DateTimeInterface;

interface UsageRecorderInterface
{
    /**
     * Registra uso de una métrica para un tenant. Si se pasa `idempotencyKey`,
     * los reintentos con la misma clave no duplican el registro.
     *
     * @param array<string, mixed> $meta
     */
    public function record(
        string $tenantId,
        string $metric,
        int $qty = 1,
        ?string $idempotencyKey = null,
        array $meta = []
    ): void;

    /** Suma del uso de una métrica desde `$since` (inclusive). */
    public function total(string $tenantId, string $metric, DateTimeInterface $since): int;
}
