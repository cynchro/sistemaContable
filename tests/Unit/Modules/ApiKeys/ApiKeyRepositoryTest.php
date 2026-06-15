<?php

namespace Tests\Unit\Modules\ApiKeys;

use App\Exceptions\NotFoundException;
use App\Modules\ApiKeys\Repositories\ApiKeyRepository;
use Tests\Unit\UnitTestCase;

class ApiKeyRepositoryTest extends UnitTestCase
{
    /** @param array<string, mixed>|false $row */
    private function pdoFetching(array|false $row, array $all = []): \PDO
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);
        $stmt->method('fetchAll')->willReturn($all);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    public function test_find_decodes_scopes_and_flags_revoked(): void
    {
        $pdo = $this->pdoFetching([
            'id'         => 'uuid-1',
            'tenant_id'  => 'tenant-1',
            'name'       => 'k',
            'prefix'     => 'mk_live_x',
            'scopes'     => json_encode(['clientes.read', 'ia.rag']),
            'revoked_at' => '2026-01-01 00:00:00',
        ]);

        $repo = new ApiKeyRepository($pdo);
        $row  = $repo->findForTenant('uuid-1', 'tenant-1');

        $this->assertSame(['clientes.read', 'ia.rag'], $row['scopes']);
        $this->assertTrue($row['revoked']);
        $this->assertArrayNotHasKey('hash', $row);
    }

    public function test_find_throws_not_found(): void
    {
        $repo = new ApiKeyRepository($this->pdoFetching(false));

        $this->expectException(NotFoundException::class);
        $repo->findForTenant('nope', 'tenant-1');
    }

    public function test_list_returns_presented_rows(): void
    {
        $pdo = $this->pdoFetching(false, [
            ['id' => 'a', 'scopes' => json_encode(['x']), 'revoked_at' => null],
            ['id' => 'b', 'scopes' => null, 'revoked_at' => '2026-01-01 00:00:00'],
        ]);

        $repo = new ApiKeyRepository($pdo);
        $rows = $repo->allForTenant('tenant-1');

        $this->assertCount(2, $rows);
        $this->assertSame(['x'], $rows[0]['scopes']);
        $this->assertFalse($rows[0]['revoked']);
        $this->assertSame([], $rows[1]['scopes']);
        $this->assertTrue($rows[1]['revoked']);
    }
}
