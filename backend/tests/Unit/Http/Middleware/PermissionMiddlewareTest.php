<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\PermissionMiddleware;
use App\Support\Auth\PermissionChecker;
use App\Support\Request;
use App\Support\Response;
use App\Exceptions\AuthException;
use App\Exceptions\ForbiddenException;
use Tests\Unit\UnitTestCase;

class PermissionMiddlewareTest extends UnitTestCase
{
    private function checkerAllowing(bool $allows): PermissionChecker
    {
        $checker = $this->createMock(PermissionChecker::class);
        $checker->method('allows')->willReturn($allows);
        return $checker;
    }

    public function test_passes_when_checker_allows(): void
    {
        $mw      = new PermissionMiddleware($this->checkerAllowing(true), 'usuarios.delete');
        $request = $this->makeRequest(['sub' => 1, 'rol' => 1]);

        $called = false;
        $result = $mw->handle($request, function (Request $req) use (&$called): Response {
            $called = true;
            return Response::success();
        });

        $this->assertTrue($called);
        $this->assertSame(200, $result->getStatus());
    }

    public function test_write_level_maps_to_write_minimum(): void
    {
        $checker = $this->createMock(PermissionChecker::class);
        $checker->expects($this->once())
            ->method('allows')
            ->with(1, 'facturas', PermissionChecker::LEVEL_WRITE)
            ->willReturn(true);

        $mw      = new PermissionMiddleware($checker, 'facturas', 'write');
        $request = $this->makeRequest(['sub' => 1, 'rol' => 1]);

        $result = $mw->handle($request, fn(Request $r): Response => Response::success());

        $this->assertSame(200, $result->getStatus());
    }

    public function test_throws_forbidden_when_checker_denies(): void
    {
        $mw      = new PermissionMiddleware($this->checkerAllowing(false), 'facturas', 'write');
        $request = $this->makeRequest(['sub' => 1, 'rol' => 2]);

        $this->expectException(ForbiddenException::class);

        $mw->handle($request, fn(Request $r): Response => Response::success());
    }

    public function test_throws_auth_exception_when_no_user(): void
    {
        $mw      = new PermissionMiddleware($this->createMock(PermissionChecker::class), 'any.permission');
        $request = $this->makeRequest(null);

        $this->expectException(AuthException::class);

        $mw->handle($request, fn(Request $r): Response => Response::success());
    }
}
