<?php

namespace App\Modules\Billing\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Exceptions\AuthException;
use App\Exceptions\NotFoundException;
use App\Modules\Billing\GatewayFactory;
use App\Modules\Billing\Requests\CheckoutRequest;
use Cynchro\Billing\BillingManager;
use Cynchro\Billing\PlanRepository;
use Cynchro\Billing\Stripe\StripeGateway;
use Cynchro\Billing\Contracts\PaymentGatewayInterface;
use Cynchro\Billing\MercadoPago\MercadoPagoGateway;

class BillingController
{
    public function __construct(
        private BillingManager $billing,
        private PlanRepository $plans,
        private GatewayFactory $gateways
    ) {
    }

    /** Inicia el checkout del plan para el tenant autenticado. */
    public function checkout(Request $request, CheckoutRequest $validated): Response
    {
        $tenantId = (string) $request->tenantId();
        $planKey  = (string) $validated->input('plan');

        $plan = $this->plans->findByKey($planKey);
        if ($plan === null) {
            throw new NotFoundException('Plan', $planKey);
        }

        $session = $this->gateways->default()->createCheckout($tenantId, $plan);

        return Response::success(['url' => $session->url, 'id' => $session->externalId], 201);
    }

    /**
     * Recibe un webhook de la pasarela (público; se valida por firma, no por JWT).
     * Verifica → normaliza → BillingManager::handleEvent (escribe entitlements).
     */
    public function webhook(Request $request): Response
    {
        $gateway = $this->gateways->make((string) $request->route('gateway'));

        if (!$this->verifySignature($gateway, $request)) {
            throw new AuthException('Invalid webhook signature.', 401);
        }

        $this->billing->handleEvent($gateway->parseWebhook($request->all()));

        return Response::success(['received' => true]);
    }

    private function verifySignature(PaymentGatewayInterface $gateway, Request $request): bool
    {
        if ($gateway instanceof StripeGateway) {
            return $gateway->verifyWebhook(
                $request->rawBody(),
                (string) $request->header('Stripe-Signature')
            );
        }

        if ($gateway instanceof MercadoPagoGateway) {
            $all    = $request->all();
            $data   = is_array($all['data'] ?? null) ? $all['data'] : [];
            $dataId = (string) ($data['id'] ?? '');

            return $gateway->verifyWebhook(
                (string) $request->header('x-signature'),
                (string) $request->header('x-request-id'),
                $dataId
            );
        }

        return false;
    }
}
