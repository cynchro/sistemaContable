<?php

namespace Tests\Unit\Support\Auth;

use App\Support\Request;
use App\Support\Auth\ApiKeyGuard;
use App\Support\Auth\ApiKeyManager;
use App\Exceptions\AuthException;
use Tests\Unit\UnitTestCase;

class ApiKeyGuardTest extends UnitTestCase
{
    /** @param array<string, mixed>|false $row */
    private function manager(array|false $row): ApiKeyManager
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return new ApiKeyManager($pdo);
    }

    private function requestWithToken(string $token): Request
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        return new Request();
    }

    public function test_returns_principal_for_valid_key(): void
    {
        $secret = bin2hex(random_bytes(24));
        $prefix = 'mk_live_' . bin2hex(random_bytes(6));
        $token  = $prefix . '_' . $secret;

        $row = [
            'id'         => 'uuid-1',
            'tenant_id'  => 'tenant-9',
            'prefix'     => $prefix,
            'hash'       => hash('sha256', $secret),
            'scopes'     => json_encode(['ia.rag']),
            'revoked_at' => null,
            'expires_at' => null,
        ];

        $guard     = new ApiKeyGuard($this->manager($row));
        $principal = $guard->authenticate($this->requestWithToken($token));

        $this->assertNotNull($principal);
        $this->assertTrue($principal->isApiKey());
        $this->assertSame('tenant-9', $principal->tenantId);
        $this->assertTrue($principal->hasScope('ia.rag'));
        $this->assertFalse($principal->hasScope('clientes.write'));
    }

    public function test_returns_null_when_not_api_key_scheme(): void
    {
        // Un bearer JWT (no empieza con mk_) no es de este guard.
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJhbGciOiJIUzI1NiJ9.payload.sig';
        $guard = new ApiKeyGuard($this->manager(false));

        $this->assertNull($guard->authenticate(new Request()));
    }

    public function test_returns_null_when_no_credential(): void
    {
        $guard = new ApiKeyGuard($this->manager(false));

        $this->assertNull($guard->authenticate(new Request()));
    }

    public function test_throws_for_invalid_key(): void
    {
        $token = 'mk_live_' . bin2hex(random_bytes(6)) . '_' . bin2hex(random_bytes(24));
        $guard = new ApiKeyGuard($this->manager(false)); // no existe en DB

        $this->expectException(AuthException::class);

        $guard->authenticate($this->requestWithToken($token));
    }
}
