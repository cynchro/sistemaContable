<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ScopeMiddleware;
use App\Support\Request;
use App\Support\Response;
use App\Support\Auth\Principal;
use App\Exceptions\AuthException;
use App\Exceptions\ForbiddenException;
use Tests\Unit\UnitTestCase;

class ScopeMiddlewareTest extends UnitTestCase
{
    private function requestWithPrincipal(Principal $principal): Request
    {
        $request = new Request();
        $request->setPrincipal($principal);
        return $request;
    }

    public function test_passes_when_scope_present(): void
    {
        $mw      = new ScopeMiddleware('clientes.read');
        $request = $this->requestWithPrincipal(
            new Principal(type: 'api_key', tenantId: 't1', scopes: ['clientes.read', 'ia.rag'])
        );

        $called = false;
        $result = $mw->handle($request, function (Request $r) use (&$called): Response {
            $called = true;
            return Response::success();
        });

        $this->assertTrue($called);
        $this->assertSame(200, $result->getStatus());
    }

    public function test_passes_with_wildcard_scope(): void
    {
        $mw      = new ScopeMiddleware('cualquier.cosa');
        $request = $this->requestWithPrincipal(
            new Principal(type: 'user', tenantId: 't1', scopes: ['*'])
        );

        $result = $mw->handle($request, fn (Request $r): Response => Response::success());

        $this->assertSame(200, $result->getStatus());
    }

    public function test_forbidden_when_scope_missing(): void
    {
        $mw      = new ScopeMiddleware('facturas.write');
        $request = $this->requestWithPrincipal(
            new Principal(type: 'api_key', tenantId: 't1', scopes: ['clientes.read'])
        );

        $this->expectException(ForbiddenException::class);

        $mw->handle($request, fn (Request $r): Response => Response::success());
    }

    public function test_auth_exception_when_no_principal(): void
    {
        $mw      = new ScopeMiddleware('clientes.read');
        $request = new Request();

        $this->expectException(AuthException::class);

        $mw->handle($request, fn (Request $r): Response => Response::success());
    }

    public function test_passes_when_no_scope_required(): void
    {
        $mw      = new ScopeMiddleware();
        $request = $this->requestWithPrincipal(
            new Principal(type: 'api_key', tenantId: 't1', scopes: [])
        );

        $result = $mw->handle($request, fn (Request $r): Response => Response::success());

        $this->assertSame(200, $result->getStatus());
    }
}
