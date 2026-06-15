<?php

namespace Tests\Unit\Support\Auth;

use App\Support\Auth\ApiKeyManager;
use Tests\Unit\UnitTestCase;

class ApiKeyManagerTest extends UnitTestCase
{
    /**
     * Construye un PDO mock cuyo SELECT devuelve la fila dada (o false).
     *
     * @param array<string, mixed>|false $row
     */
    private function pdoReturning(array|false $row): \PDO
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    /** Construye un token y la fila persistida coherente para ese secreto. */
    private function tokenAndRow(array $overrides = []): array
    {
        $secret = bin2hex(random_bytes(24));
        $prefix = 'mk_live_' . bin2hex(random_bytes(6));
        $token  = $prefix . '_' . $secret;

        $row = array_merge([
            'id'         => 'uuid-1',
            'tenant_id'  => 'tenant-1',
            'name'       => 'CI key',
            'prefix'     => $prefix,
            'hash'       => hash('sha256', $secret),
            'scopes'     => json_encode(['clientes.read']),
            'revoked_at' => null,
            'expires_at' => null,
        ], $overrides);

        return [$token, $row];
    }

    public function test_verify_accepts_valid_token(): void
    {
        [$token, $row] = $this->tokenAndRow();
        $manager       = new ApiKeyManager($this->pdoReturning($row));

        $result = $manager->verify($token);

        $this->assertNotNull($result);
        $this->assertSame('tenant-1', $result['tenant_id']);
        $this->assertSame(['clientes.read'], $result['scopes']);
    }

    public function test_verify_rejects_wrong_secret(): void
    {
        [, $row]  = $this->tokenAndRow();
        $manager  = new ApiKeyManager($this->pdoReturning($row));

        // Token con el mismo prefix pero secreto distinto.
        $forged = $row['prefix'] . '_' . bin2hex(random_bytes(24));

        $this->assertNull($manager->verify($forged));
    }

    public function test_verify_rejects_revoked(): void
    {
        [$token, $row] = $this->tokenAndRow(['revoked_at' => '2026-01-01 00:00:00']);
        $manager       = new ApiKeyManager($this->pdoReturning($row));

        $this->assertNull($manager->verify($token));
    }

    public function test_verify_rejects_expired(): void
    {
        [$token, $row] = $this->tokenAndRow(['expires_at' => '2000-01-01 00:00:00']);
        $manager       = new ApiKeyManager($this->pdoReturning($row));

        $this->assertNull($manager->verify($token));
    }

    public function test_verify_rejects_unknown_prefix(): void
    {
        [$token]  = $this->tokenAndRow();
        $manager  = new ApiKeyManager($this->pdoReturning(false));

        $this->assertNull($manager->verify($token));
    }

    public function test_verify_rejects_non_prefixed_token(): void
    {
        $manager = new ApiKeyManager($this->pdoReturning(false));

        $this->assertNull($manager->verify('not-an-api-key'));
    }

    public function test_issue_returns_token_with_expected_shape(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $manager = new ApiKeyManager($pdo);
        $issued  = $manager->issue('tenant-1', 'mi key', ['clientes.read'], 'live');

        $this->assertStringStartsWith('mk_live_', $issued['token']);
        $this->assertStringStartsWith('mk_live_', $issued['prefix']);
        $this->assertStringStartsWith($issued['prefix'] . '_', $issued['token']);
        $this->assertNotEmpty($issued['id']);
    }
}
