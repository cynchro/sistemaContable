<?php

namespace Tests\Unit\Http\Controllers;

use PDO;
use PDOException;
use PDOStatement;
use App\Http\Controllers\HealthController;
use App\Support\Cache\ArrayCache;
use Tests\Unit\UnitTestCase;

/**
 * Health check más profundo: `db` es crítico (200/503), `cache` se reporta como
 * degradación pero no cambia el código de estado (la instancia sigue sirviendo).
 */
class HealthControllerTest extends UnitTestCase
{
    public function test_healthy_when_db_and_cache_ok(): void
    {
        $health = new HealthController($this->workingPdo(), new ArrayCache());

        $res  = $health->check($this->makeRequest());
        $body = (array) $res->getBody();

        $this->assertSame(200, $res->getStatus());
        $this->assertSame('ok', $body['data']['status']);
        $this->assertSame('ok', $body['data']['checks']['db']);
        $this->assertSame('ok', $body['data']['checks']['cache']);
    }

    public function test_cache_unavailable_is_reported_but_still_serves_200(): void
    {
        $inertCache = new class extends ArrayCache {
            public function available(): bool
            {
                return false;
            }
        };

        $health = new HealthController($this->workingPdo(), $inertCache);
        $res    = $health->check($this->makeRequest());

        // db está OK → la instancia sigue sirviendo (200), aunque el cache esté degradado.
        $this->assertSame(200, $res->getStatus());
        $this->assertSame('degraded', ((array) $res->getBody())['data']['checks']['cache']);
    }

    public function test_down_when_db_unreachable(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willThrowException(new PDOException('connection refused'));

        $health = new HealthController($pdo, new ArrayCache());
        $res    = $health->check($this->makeRequest());
        $body   = (array) $res->getBody();

        $this->assertSame(503, $res->getStatus());
        $this->assertSame('down', $body['data']['status']);
        $this->assertSame('unreachable', $body['data']['checks']['db']);
    }

    private function workingPdo(): PDO
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($this->createMock(PDOStatement::class));

        return $pdo;
    }
}
