<?php

namespace Tests\Unit\Support\Usage;

use App\Support\Usage\DbUsageRecorder;
use Tests\Unit\UnitTestCase;

class DbUsageRecorderTest extends UnitTestCase
{
    public function test_total_sums_quantity(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn('42');

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $recorder = new DbUsageRecorder($pdo);
        $total    = $recorder->total('tenant-1', 'api.calls', new \DateTimeImmutable('2026-06-01'));

        $this->assertSame(42, $total);
    }

    public function test_record_executes_insert_with_params(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                // tenant, metric, qty, idempotency_key, meta(json)
                return $params[0] === 'tenant-1'
                    && $params[1] === 'api.calls'
                    && $params[2] === 3
                    && $params[3] === 'idem-1'
                    && $params[4] === json_encode(['route' => '/x']);
            }))
            ->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        (new DbUsageRecorder($pdo))->record('tenant-1', 'api.calls', 3, 'idem-1', ['route' => '/x']);
    }

    public function test_record_without_meta_passes_null(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(fn (array $p): bool => $p[3] === null && $p[4] === null))
            ->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        (new DbUsageRecorder($pdo))->record('tenant-1', 'api.calls');
    }
}
