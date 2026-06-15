<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\AuthMiddleware;
use App\Support\Request;
use App\Support\Response;
use App\Support\JWTConfig;
use App\Support\Auth\JwtGuard;
use App\Support\Auth\ApiKeyGuard;
use App\Support\Auth\ApiKeyManager;
use App\Exceptions\AuthException;
use Tests\Unit\UnitTestCase;

class AuthMiddlewareTest extends UnitTestCase
{
    /** @param array<string, mixed>|false $row */
    private function middleware(array|false $row): AuthMiddleware
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return new AuthMiddleware(new JwtGuard($pdo), new ApiKeyGuard(new ApiKeyManager($pdo)));
    }

    public function test_authenticates_via_jwt_and_sets_user(): void
    {
        $token = JWTConfig::generateToken(7, 'tenant-x');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $mw      = $this->middleware(['id' => 7]);
        $request = new Request();

        $result = $mw->handle($request, fn (Request $r): Response => Response::success());

        $this->assertSame(200, $result->getStatus());
        $this->assertNotNull($request->principal());
        $this->assertTrue($request->principal()->isUser());
        // Retrocompatibilidad: TenantMiddleware/PermissionMiddleware leen user().
        $this->assertSame('tenant-x', $request->user()['tenant_id']);
    }

    public function test_throws_when_no_credential(): void
    {
        $mw      = $this->middleware(false);
        $request = new Request();

        $this->expectException(AuthException::class);

        $mw->handle($request, fn (Request $r): Response => Response::success());
    }

    public function test_authenticates_via_api_key(): void
    {
        $secret = bin2hex(random_bytes(24));
        $prefix = 'mk_live_' . bin2hex(random_bytes(6));
        $token  = $prefix . '_' . $secret;
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $mw = $this->middleware([
            'id'         => 'key-1',
            'tenant_id'  => 'tenant-7',
            'prefix'     => $prefix,
            'hash'       => hash('sha256', $secret),
            'scopes'     => json_encode(['clientes.read']),
            'revoked_at' => null,
            'expires_at' => null,
        ]);
        $request = new Request();

        $result = $mw->handle($request, fn (Request $r): Response => Response::success());

        $this->assertSame(200, $result->getStatus());
        $this->assertTrue($request->principal()->isApiKey());
        $this->assertSame('tenant-7', $request->principal()->tenantId);
        $this->assertSame('key-1', $request->user()['api_key_id']);
    }
}
