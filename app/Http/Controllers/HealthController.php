<?php

namespace App\Http\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Support\Contracts\CacheInterface;

class HealthController
{
    public function __construct(
        private \PDO $pdo,
        private CacheInterface $cache
    ) {
    }

    /**
     * Health/readiness check. Distingue severidades:
     *   - `db` es crítico para servir → si falla, status `down` + HTTP 503
     *     (saca la instancia del balanceador).
     *   - `cache` es una degradación (rate limiting / anti-replay de webhooks),
     *     no una caída → se reporta, pero no cambia el código de estado.
     */
    public function check(Request $request): Response
    {
        $db    = $this->pingDatabase();
        $cache = $this->cache->available();

        return Response::success([
            'status' => $db ? 'ok' : 'down',
            'php'    => PHP_VERSION,
            'checks' => [
                'db'    => $db ? 'ok' : 'unreachable',
                'cache' => $cache ? 'ok' : 'degraded',
            ],
        ], $db ? 200 : 503);
    }

    private function pingDatabase(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
