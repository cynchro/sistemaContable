<?php

namespace App\Modules\Billing;

use PDO;
use App\Support\Config;
use App\Support\ServiceProvider as BaseServiceProvider;
use Cynchro\Billing\BillingManager;
use Cynchro\Billing\PlanRepository;
use Cynchro\Billing\TenantEntitlementWriter;
use Cynchro\Billing\SubscriptionRepository;
use Cynchro\Billing\Contracts\EntitlementWriterInterface;

/**
 * Registra el billing en el container — solo si el SDK está instalado
 * (cynchro/modux-billing). Si no, el módulo queda inactivo.
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        if (!class_exists(BillingManager::class)) {
            return;
        }

        $c = $this->container;

        $c->singleton(PlanRepository::class, fn () => new PlanRepository($c->get(PDO::class)));
        $c->singleton(SubscriptionRepository::class, fn () => new SubscriptionRepository($c->get(PDO::class)));
        $c->singleton(EntitlementWriterInterface::class, fn () => new TenantEntitlementWriter($c->get(PDO::class)));

        $c->singleton(BillingManager::class, fn () => new BillingManager(
            $c->get(PlanRepository::class),
            $c->get(SubscriptionRepository::class),
            $c->get(EntitlementWriterInterface::class),
        ));

        $c->singleton(GatewayFactory::class, fn () => new GatewayFactory(
            (array) Config::all('billing')
        ));
    }
}
