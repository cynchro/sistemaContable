<?php

namespace Tests\Unit\Http\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Support\Entitlements\Entitlement;
use App\Support\Entitlements\EntitlementSet;
use App\Http\Middleware\EntitlementMiddleware;
use App\Exceptions\AuthException;
use App\Exceptions\PaymentRequiredException;
use App\Support\Contracts\EntitlementResolverInterface;
use Tests\Unit\UnitTestCase;

class EntitlementMiddlewareTest extends UnitTestCase
{
    private function resolverReturning(EntitlementSet $set): EntitlementResolverInterface
    {
        $resolver = $this->createMock(EntitlementResolverInterface::class);
        $resolver->method('for')->willReturn($set);
        return $resolver;
    }

    public function test_passes_when_feature_enabled(): void
    {
        $resolver = $this->resolverReturning(
            new EntitlementSet(['ia.rag' => new Entitlement('ia.rag', 'flag')])
        );
        $mw      = new EntitlementMiddleware($resolver, 'ia.rag');
        $request = $this->makeRequest(['sub' => 1], 'tenant-1');

        $called = false;
        $result = $mw->handle($request, function (Request $r) use (&$called): Response {
            $called = true;
            return Response::success();
        });

        $this->assertTrue($called);
        $this->assertSame(200, $result->getStatus());
    }

    public function test_402_when_feature_missing(): void
    {
        $resolver = $this->resolverReturning(new EntitlementSet([]));
        $mw       = new EntitlementMiddleware($resolver, 'ia.rag');
        $request  = $this->makeRequest(['sub' => 1], 'tenant-1');

        $this->expectException(PaymentRequiredException::class);

        $mw->handle($request, fn (Request $r): Response => Response::success());
    }

    public function test_402_when_feature_disabled(): void
    {
        $resolver = $this->resolverReturning(
            new EntitlementSet(['ia.rag' => new Entitlement('ia.rag', 'flag', null, false)])
        );
        $mw      = new EntitlementMiddleware($resolver, 'ia.rag');
        $request = $this->makeRequest(['sub' => 1], 'tenant-1');

        $this->expectException(PaymentRequiredException::class);

        $mw->handle($request, fn (Request $r): Response => Response::success());
    }

    public function test_auth_exception_when_no_tenant(): void
    {
        $resolver = $this->resolverReturning(new EntitlementSet([]));
        $mw       = new EntitlementMiddleware($resolver, 'ia.rag');
        $request  = $this->makeRequest(['sub' => 1]); // sin tenantId

        $this->expectException(AuthException::class);

        $mw->handle($request, fn (Request $r): Response => Response::success());
    }
}
