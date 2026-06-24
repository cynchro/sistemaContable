<?php

namespace Tests\Unit\Support\Entitlements;

use DateTimeImmutable;
use App\Support\Entitlements\DbEntitlementResolver;
use Tests\Unit\UnitTestCase;

class DbEntitlementResolverTest extends UnitTestCase
{
    /** @param list<array<string, mixed>> $rows */
    private function resolver(array $rows): DbEntitlementResolver
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return new DbEntitlementResolver($pdo);
    }

    public function test_builds_set_from_rows(): void
    {
        $set = $this->resolver([
            [
                'feature' => 'ia.rag', 'type' => 'flag', 'limit_value' => null, 'enabled' => 1,
                'period_start' => null, 'period_end' => null, 'expires_at' => null,
            ],
            [
                'feature' => 'api.calls', 'type' => 'quota', 'limit_value' => 1000, 'enabled' => 1,
                'period_start' => '2026-06-01 00:00:00', 'period_end' => '2026-07-01 00:00:00',
                'expires_at' => null,
            ],
        ])->for('tenant-1');

        $this->assertTrue($set->allows('ia.rag'));
        $this->assertSame(1000, $set->limit('api.calls'));
        $this->assertInstanceOf(DateTimeImmutable::class, $set->get('api.calls')->periodStart);
        $this->assertSame('quota', $set->get('api.calls')->type);
    }

    public function test_disabled_row_is_not_allowed(): void
    {
        $set = $this->resolver([
            [
                'feature' => 'ia.rag', 'type' => 'flag', 'limit_value' => null, 'enabled' => 0,
                'period_start' => null, 'period_end' => null, 'expires_at' => null,
            ],
        ])->for('tenant-1');

        $this->assertFalse($set->allows('ia.rag'));
    }

    public function test_empty_when_no_rows(): void
    {
        $set = $this->resolver([])->for('tenant-1');

        $this->assertFalse($set->allows('cualquier.cosa'));
        $this->assertNull($set->get('cualquier.cosa'));
    }
}
