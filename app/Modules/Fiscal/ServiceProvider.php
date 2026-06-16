<?php

namespace App\Modules\Fiscal;

use PDO;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Fiscal\Repositories\TributoRepository;
use App\Modules\Fiscal\Repositories\VencimientoRepository;
use App\Modules\Fiscal\Services\TributoService;
use App\Modules\Fiscal\Services\VencimientoService;
use App\Modules\Fiscal\Controllers\TributoController;
use App\Modules\Fiscal\Controllers\VencimientoController;

/**
 * Wiring del módulo Fiscal (obligaciones fiscales, de sistemaCuarto). Reusa
 * EmpresaRepository del Compartido (el contribuyente es la empresa canónica).
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $c = $this->container;

        $c->singleton(TributoRepository::class, fn () => new TributoRepository($c->get(PDO::class)));
        $c->singleton(TributoService::class, fn () => new TributoService($c->get(TributoRepository::class)));
        $c->singleton(TributoController::class, fn () => new TributoController($c->get(TributoService::class)));

        $c->singleton(VencimientoRepository::class, fn () => new VencimientoRepository($c->get(PDO::class)));
        $c->singleton(VencimientoService::class, fn () => new VencimientoService(
            $c->get(VencimientoRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(
            VencimientoController::class,
            fn () => new VencimientoController($c->get(VencimientoService::class)),
        );
    }
}
