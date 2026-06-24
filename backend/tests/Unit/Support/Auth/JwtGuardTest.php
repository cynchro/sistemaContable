<?php

namespace Tests\Unit\Support\Auth;

use App\Support\Request;
use App\Support\JWTConfig;
use App\Support\Auth\JwtGuard;
use App\Exceptions\AuthException;
use Tests\Unit\UnitTestCase;

class JwtGuardTest extends UnitTestCase
{
    /** @param array<string, mixed>|false $userRow */
    private function pdo(array|false $userRow): \PDO
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($userRow);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    private function requestWithBearer(string $token): Request
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        return new Request();
    }

    public function test_authenticates_valid_token(): void
    {
        $token     = JWTConfig::generateToken(7, 'tenant-x');
        $guard     = new JwtGuard($this->pdo(['id' => 7]));
        $principal = $guard->authenticate($this->requestWithBearer($token));

        $this->assertNotNull($principal);
        $this->assertTrue($principal->isUser());
        $this->assertSame('tenant-x', $principal->tenantId);
        $this->assertSame(7, $principal->userId);
        $this->assertTrue($principal->hasScope('cualquier.cosa')); // '*'
        $this->assertSame('tenant-x', $principal->claims['tenant_id']);
    }

    public function test_returns_null_without_bearer(): void
    {
        $guard = new JwtGuard($this->pdo(false));

        $this->assertNull($guard->authenticate(new Request()));
    }

    public function test_returns_null_for_api_key_token(): void
    {
        $guard = new JwtGuard($this->pdo(false));

        $this->assertNull(
            $guard->authenticate($this->requestWithBearer('mk_live_abc_def'))
        );
    }

    public function test_throws_on_invalid_token(): void
    {
        $guard = new JwtGuard($this->pdo(false));

        $this->expectException(AuthException::class);

        $guard->authenticate($this->requestWithBearer('not-a-valid-jwt'));
    }

    public function test_throws_on_revoked_token(): void
    {
        $token = JWTConfig::generateToken(7, 'tenant-x');
        $guard = new JwtGuard($this->pdo(false)); // usuario/token no encontrado → revocado

        $this->expectException(AuthException::class);

        $guard->authenticate($this->requestWithBearer($token));
    }
}
