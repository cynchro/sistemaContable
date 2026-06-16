<?php

namespace App\Modules\Contribuyentes;

use PDO;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Contribuyentes\Repositories\SocioRepository;
use App\Modules\Contribuyentes\Services\SocioService;
use App\Modules\Contribuyentes\Controllers\SocioController;
use App\Modules\Contribuyentes\Repositories\CredencialRepository;
use App\Modules\Contribuyentes\Services\CredencialService;
use App\Modules\Contribuyentes\Controllers\CredencialController;

/**
 * Wiring del módulo Contribuyentes (CRM del contribuyente, de sistemaCuarto).
 * El contribuyente es la empresa canónica (Compartido); acá viven sus sub-entidades
 * de CRM (socios, y a futuro cuentas bancarias, documentación).
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $c = $this->container;

        $c->singleton(SocioRepository::class, fn () => new SocioRepository($c->get(PDO::class)));
        $c->singleton(SocioService::class, fn () => new SocioService(
            $c->get(SocioRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(SocioController::class, fn () => new SocioController($c->get(SocioService::class)));

        $c->singleton(CredencialRepository::class, fn () => new CredencialRepository($c->get(PDO::class)));
        $c->singleton(CredencialService::class, fn () => new CredencialService(
            $c->get(CredencialRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(
            CredencialController::class,
            fn () => new CredencialController($c->get(CredencialService::class)),
        );
    }
}
