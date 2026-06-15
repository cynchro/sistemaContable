<?php

namespace Tests\Unit\Modules\Billing;

use App\Support\Request;
use App\Exceptions\AuthException;
use App\Modules\Billing\GatewayFactory;
use App\Modules\Billing\Controllers\BillingController;
use Cynchro\Billing\BillingManager;
use Cynchro\Billing\PlanRepository;
use Cynchro\Billing\TenantEntitlementWriter;
use Cynchro\Billing\SubscriptionRepository;
use Tests\Unit\UnitTestCase;

class BillingControllerTest extends UnitTestCase
{
    private const SECRET = 'whsec_test';

    protected function tearDown(): void
    {
        Request::setTestInputStream(null);
        parent::tearDown();
    }

    /**
     * Controller con objetos reales de billing sobre un PDO mock que devuelve una
     * suscripción (lookup por external_id) y los entitlements del plan.
     */
    private function controller(): BillingController
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 's1', 'tenant_id' => 't1', 'plan_id' => 'p1', 'status' => 'active',
            'gateway' => 'stripe', 'external_id' => 'sub_1', 'current_period_end' => null,
        ]);
        $stmt->method('fetchAll')->willReturn([
            ['feature' => 'ia.rag', 'type' => 'flag', 'limit_value' => null],
        ]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $plans   = new PlanRepository($pdo);
        $billing = new BillingManager($plans, new SubscriptionRepository($pdo), new TenantEntitlementWriter($pdo));

        $factory = new GatewayFactory([
            'default'  => 'stripe',
            'gateways' => ['stripe' => [
                'api_key' => '', 'webhook_secret' => self::SECRET, 'success_url' => '', 'cancel_url' => '',
            ]],
        ]);

        return new BillingController($billing, $plans, $factory);
    }

    private function webhookRequest(string $payload, string $signature): Request
    {
        Request::setTestInputStream($payload);
        $_SERVER['CONTENT_TYPE']         = 'application/json';
        $_SERVER['HTTP_STRIPE_SIGNATURE'] = $signature;
        $request = new Request();
        $request->setRouteParams(['gateway' => 'stripe']);
        return $request;
    }

    public function test_webhook_valid_signature_is_processed(): void
    {
        $payload = '{"type":"customer.subscription.updated",'
            . '"data":{"object":{"id":"sub_1","current_period_end":1799999999}}}';
        $ts      = time();
        $v1      = hash_hmac('sha256', $ts . '.' . $payload, self::SECRET);

        $response = $this->controller()->webhook($this->webhookRequest($payload, "t={$ts},v1={$v1}"));

        $this->assertSame(200, $response->getStatus());
    }

    public function test_webhook_invalid_signature_rejected(): void
    {
        $payload = '{"type":"customer.subscription.updated","data":{"object":{"id":"sub_1"}}}';

        $this->expectException(AuthException::class);

        $this->controller()->webhook($this->webhookRequest($payload, 't=' . time() . ',v1=bad'));
    }
}
