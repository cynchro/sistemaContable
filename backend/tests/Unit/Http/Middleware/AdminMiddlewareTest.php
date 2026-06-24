<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\AdminMiddleware;
use App\Support\Auth\PermissionChecker;
use App\Support\Request;
use App\Support\Response;
use App\Exceptions\ForbiddenException;
use Tests\Unit\UnitTestCase;

class AdminMiddlewareTest extends UnitTestCase
{
    private function checkerAllowing(bool $allows): PermissionChecker
    {
        $checker = $this->createMock(PermissionChecker::class);
        $checker->method('allows')->willReturn($allows);
        return $checker;
    }

    public function test_passes_when_role_has_super_permission(): void
    {
        $mw      = new AdminMiddleware($this->checkerAllowing(true));
        $request = $this->makeRequest(['sub' => 1, 'rol' => 7]);

        $called = false;
        $result = $mw->handle($request, function (Request $req) use (&$called): Response {
            $called = true;
            return Response::success();
        });

        $this->assertTrue($called);
        $this->assertSame(200, $result->getStatus());
    }

    public function test_throws_forbidden_when_role_lacks_super_permission(): void
    {
        $mw      = new AdminMiddleware($this->checkerAllowing(false));
        $request = $this->makeRequest(['sub' => 1, 'rol' => 2]);

        $this->expectException(ForbiddenException::class);

        $mw->handle($request, fn(Request $r): Response => Response::success());
    }

    public function test_throws_forbidden_when_no_user(): void
    {
        $mw      = new AdminMiddleware($this->createMock(PermissionChecker::class));
        $request = $this->makeRequest(null);

        $this->expectException(ForbiddenException::class);

        $mw->handle($request, fn(Request $r): Response => Response::success());
    }
}
