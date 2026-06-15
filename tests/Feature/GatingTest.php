<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\EntitlementMiddleware;
use App\Http\Middleware\QuotaMiddleware;
use Tests\Feature\Fixtures\PingController;

/**
 * Gating SaaS a nivel HTTP contra DB real: entitlements (402 si falta la feature)
 * y cuotas (429 + Retry-After al agotarse). Se ejercita sobre rutas ad-hoc para
 * no acoplar la prueba a que un módulo del base exponga endpoints protegidos.
 */
class GatingTest extends FeatureTestCase
{
    private const ENTITLEMENT_ROUTE = '/test/gated';
    private const QUOTA_ROUTE        = '/test/metered';

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerRoute('GET', self::ENTITLEMENT_ROUTE, [PingController::class, 'index'], [
            AuthMiddleware::class,
            TenantMiddleware::class,
            EntitlementMiddleware::class . ':reports.export',
        ]);

        $this->registerRoute('GET', self::QUOTA_ROUTE, [PingController::class, 'index'], [
            AuthMiddleware::class,
            TenantMiddleware::class,
            QuotaMiddleware::class . ':api.calls',
        ]);
    }

    public function test_entitlement_missing_returns_402(): void
    {
        $ctx = $this->actingAsUser();

        $res = $this->getJson(self::ENTITLEMENT_ROUTE, $this->bearer($ctx['token']));

        $this->assertSame(402, $res['status']);
    }

    public function test_entitlement_granted_passes(): void
    {
        $ctx = $this->actingAsUser();
        $this->grantFlag($ctx['tenantId'], 'reports.export');

        $res = $this->getJson(self::ENTITLEMENT_ROUTE, $this->bearer($ctx['token']));

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['data']['pong']);
    }

    public function test_quota_with_remaining_passes(): void
    {
        $ctx = $this->actingAsUser();
        $this->grantQuota($ctx['tenantId'], 'api.calls', 5);
        $this->recordUsage($ctx['tenantId'], 'api.calls', 2);

        $res = $this->getJson(self::QUOTA_ROUTE, $this->bearer($ctx['token']));

        $this->assertSame(200, $res['status']);
    }

    public function test_quota_exhausted_returns_429_with_retry_after(): void
    {
        $ctx = $this->actingAsUser();
        $this->grantQuota($ctx['tenantId'], 'api.calls', 2);
        $this->recordUsage($ctx['tenantId'], 'api.calls', 2);

        $res = $this->getJson(self::QUOTA_ROUTE, $this->bearer($ctx['token']));

        $this->assertSame(429, $res['status']);
        $this->assertArrayHasKey('Retry-After', $res['headers']);
        $this->assertGreaterThan(0, (int) $res['headers']['Retry-After']);
    }

    public function test_quota_without_entitlement_returns_402(): void
    {
        $ctx = $this->actingAsUser();

        $res = $this->getJson(self::QUOTA_ROUTE, $this->bearer($ctx['token']));

        $this->assertSame(402, $res['status']);
    }
}
