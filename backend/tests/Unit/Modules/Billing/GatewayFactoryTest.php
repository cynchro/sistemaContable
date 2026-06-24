<?php

namespace Tests\Unit\Modules\Billing;

use App\Modules\Billing\GatewayFactory;
use Cynchro\Billing\Stripe\StripeGateway;
use Cynchro\Billing\MercadoPago\MercadoPagoGateway;
use Tests\Unit\UnitTestCase;

class GatewayFactoryTest extends UnitTestCase
{
    private function factory(): GatewayFactory
    {
        return new GatewayFactory([
            'default'  => 'stripe',
            'gateways' => [
                'stripe' => [
                    'api_key' => 'sk', 'webhook_secret' => 'wh',
                    'success_url' => 's', 'cancel_url' => 'c',
                ],
                'mercadopago' => [
                    'access_token' => 'at', 'webhook_secret' => 'wh', 'back_url' => 'b',
                ],
            ],
        ]);
    }

    public function test_make_stripe(): void
    {
        $this->assertInstanceOf(StripeGateway::class, $this->factory()->make('stripe'));
    }

    public function test_make_mercadopago(): void
    {
        $this->assertInstanceOf(MercadoPagoGateway::class, $this->factory()->make('mercadopago'));
    }

    public function test_default_uses_config(): void
    {
        $this->assertInstanceOf(StripeGateway::class, $this->factory()->default());
    }

    public function test_unknown_gateway_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->factory()->make('paypal');
    }
}
