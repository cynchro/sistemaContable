<?php

namespace Tests\Unit\Http\Middleware;

use PDO;
use PDOStatement;
use App\Http\Middleware\TenantMiddleware;
use App\Support\Request;
use App\Support\Response;
use App\Exceptions\AuthException;
use Tests\Unit\UnitTestCase;

class TenantMiddlewareTest extends UnitTestCase
{
    private function makePdo(bool $tenantExists = true): PDO
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($tenantExists ? ['id' => 'abc-123'] : false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    public function test_sets_tenant_id_from_user_payload(): void
    {
        $middleware = new TenantMiddleware($this->makePdo(true));
        $request    = $this->makeRequest(['sub' => 42, 'tenant_id' => 'abc-123']);

        $called = false;
        $middleware->handle($request, function (Request $req) use (&$called): Response {
            $called = true;
            $this->assertSame('abc-123', $req->tenantId());
            return Response::success([]);
        });

        $this->assertTrue($called);
    }

    public function test_throws_when_tenant_not_found_in_db(): void
    {
        $middleware = new TenantMiddleware($this->makePdo(false));
        $request    = $this->makeRequest(['sub' => 42, 'tenant_id' => 'nonexistent']);

        $this->expectException(AuthException::class);
        $middleware->handle($request, fn () => Response::success([]));
    }

    public function test_throws_when_user_not_set(): void
    {
        $middleware = new TenantMiddleware($this->makePdo());
        $request    = $this->makeRequest(null);

        $this->expectException(AuthException::class);
        $middleware->handle($request, fn () => Response::success([]));
    }

    public function test_throws_when_tenant_id_missing_from_payload(): void
    {
        $middleware = new TenantMiddleware($this->makePdo());
        $request    = $this->makeRequest(['sub' => 42]);

        $this->expectException(AuthException::class);
        $middleware->handle($request, fn () => Response::success([]));
    }

    public function test_throws_when_tenant_id_is_empty_string(): void
    {
        $middleware = new TenantMiddleware($this->makePdo());
        $request    = $this->makeRequest(['sub' => 42, 'tenant_id' => '']);

        $this->expectException(AuthException::class);
        $middleware->handle($request, fn () => Response::success([]));
    }
}
