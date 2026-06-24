<?php

namespace Tests\Unit\Http\Middleware;

use DateTimeImmutable;
use App\Support\Request;
use App\Support\Response;
use App\Support\Entitlements\Entitlement;
use App\Support\Entitlements\EntitlementSet;
use App\Http\Middleware\QuotaMiddleware;
use App\Exceptions\AuthException;
use App\Exceptions\QuotaExceededException;
use App\Exceptions\PaymentRequiredException;
use App\Support\Contracts\UsageRecorderInterface;
use App\Support\Contracts\EntitlementResolverInterface;
use Tests\Unit\UnitTestCase;

class QuotaMiddlewareTest extends UnitTestCase
{
    private function resolver(EntitlementSet $set): EntitlementResolverInterface
    {
        $resolver = $this->createMock(EntitlementResolverInterface::class);
        $resolver->method('for')->willReturn($set);
        return $resolver;
    }

    private function usage(int $used): UsageRecorderInterface
    {
        $usage = $this->createMock(UsageRecorderInterface::class);
        $usage->method('total')->willReturn($used);
        return $usage;
    }

    private function quotaSet(?int $limit, ?DateTimeImmutable $periodEnd = null): EntitlementSet
    {
        return new EntitlementSet([
            'api.calls' => new Entitlement(
                'api.calls',
                'quota',
                $limit,
                true,
                new DateTimeImmutable('2026-06-01'),
                $periodEnd
            ),
        ]);
    }

    public function test_passes_when_quota_available(): void
    {
        $mw      = new QuotaMiddleware($this->resolver($this->quotaSet(1000)), $this->usage(300), 'api.calls');
        $request = $this->makeRequest(['sub' => 1], 'tenant-1');

        $called = false;
        $result = $mw->handle($request, function (Request $r) use (&$called): Response {
            $called = true;
            return Response::success();
        });

        $this->assertTrue($called);
        $this->assertSame(200, $result->getStatus());
    }

    public function test_429_when_quota_exhausted(): void
    {
        $mw      = new QuotaMiddleware($this->resolver($this->quotaSet(1000)), $this->usage(1000), 'api.calls');
        $request = $this->makeRequest(['sub' => 1], 'tenant-1');

        $this->expectException(QuotaExceededException::class);

        $mw->handle($request, fn (Request $r): Response => Response::success());
    }

    public function test_429_carries_retry_after_from_period_end(): void
    {
        $set     = $this->quotaSet(1000, new DateTimeImmutable('+1 day'));
        $mw      = new QuotaMiddleware($this->resolver($set), $this->usage(1000), 'api.calls');
        $request = $this->makeRequest(['sub' => 1], 'tenant-1');

        try {
            $mw->handle($request, fn (Request $r): Response => Response::success());
            $this->fail('Expected QuotaExceededException');
        } catch (QuotaExceededException $e) {
            $this->assertNotNull($e->getRetryAfter());
            $this->assertGreaterThan(0, $e->getRetryAfter());
        }
    }

    public function test_passes_when_unlimited_without_counting(): void
    {
        $usage = $this->createMock(UsageRecorderInterface::class);
        $usage->expects($this->never())->method('total'); // ilimitado → no cuenta

        $mw      = new QuotaMiddleware($this->resolver($this->quotaSet(null)), $usage, 'api.calls');
        $request = $this->makeRequest(['sub' => 1], 'tenant-1');

        $result = $mw->handle($request, fn (Request $r): Response => Response::success());
        $this->assertSame(200, $result->getStatus());
    }

    public function test_402_when_feature_missing(): void
    {
        $mw      = new QuotaMiddleware($this->resolver(new EntitlementSet([])), $this->usage(0), 'api.calls');
        $request = $this->makeRequest(['sub' => 1], 'tenant-1');

        $this->expectException(PaymentRequiredException::class);

        $mw->handle($request, fn (Request $r): Response => Response::success());
    }

    public function test_auth_exception_when_no_tenant(): void
    {
        $mw      = new QuotaMiddleware($this->resolver($this->quotaSet(1000)), $this->usage(0), 'api.calls');
        $request = $this->makeRequest(['sub' => 1]); // sin tenantId

        $this->expectException(AuthException::class);

        $mw->handle($request, fn (Request $r): Response => Response::success());
    }
}
