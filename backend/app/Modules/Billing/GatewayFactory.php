<?php

namespace App\Modules\Billing;

use Cynchro\Billing\Contracts\PaymentGatewayInterface;
use Cynchro\Billing\Stripe\StripeGateway;
use Cynchro\Billing\Stripe\CurlHttpClient as StripeHttpClient;
use Cynchro\Billing\MercadoPago\MercadoPagoGateway;
use Cynchro\Billing\MercadoPago\CurlHttpClient as MercadoPagoHttpClient;

/**
 * Construye el adaptador de pasarela a partir de la config (config/billing.php).
 */
final class GatewayFactory
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
    }

    public function make(string $name): PaymentGatewayInterface
    {
        $gateways = $this->config['gateways'] ?? [];
        $gw       = is_array($gateways) ? ($gateways[$name] ?? null) : null;

        if (!is_array($gw)) {
            throw new \InvalidArgumentException("Unknown billing gateway: {$name}");
        }

        return match ($name) {
            'stripe' => new StripeGateway(
                new StripeHttpClient((string) ($gw['api_key'] ?? '')),
                (string) ($gw['webhook_secret'] ?? ''),
                (string) ($gw['success_url'] ?? ''),
                (string) ($gw['cancel_url'] ?? ''),
            ),
            'mercadopago' => new MercadoPagoGateway(
                new MercadoPagoHttpClient((string) ($gw['access_token'] ?? '')),
                (string) ($gw['webhook_secret'] ?? ''),
                (string) ($gw['back_url'] ?? ''),
            ),
            default => throw new \InvalidArgumentException("Unsupported billing gateway: {$name}"),
        };
    }

    public function default(): PaymentGatewayInterface
    {
        return $this->make((string) ($this->config['default'] ?? 'stripe'));
    }
}
