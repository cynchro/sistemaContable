<?php

namespace App\Modules\Honorarios;

use PDO;
use App\Support\DB;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Honorarios\Calc\HonorarioCalculator;
use App\Modules\Honorarios\Repositories\ServicioRepository;
use App\Modules\Honorarios\Repositories\FactorComplejidadRepository;
use App\Modules\Honorarios\Repositories\HonorarioRepository;
use App\Modules\Honorarios\Services\ServicioService;
use App\Modules\Honorarios\Services\FactorComplejidadService;
use App\Modules\Honorarios\Services\HonorarioService;
use App\Modules\Honorarios\Controllers\ServicioController;
use App\Modules\Honorarios\Controllers\FactorComplejidadController;
use App\Modules\Honorarios\Controllers\HonorarioController;

/**
 * Wiring del módulo Honorarios (de sistemaCuarto). Catálogos por tenant (servicios,
 * factores) y documento de honorarios por empresa (contribuyente).
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $c = $this->container;

        $c->singleton(ServicioRepository::class, fn () => new ServicioRepository($c->get(PDO::class)));
        $c->singleton(ServicioService::class, fn () => new ServicioService($c->get(ServicioRepository::class)));
        $c->singleton(ServicioController::class, fn () => new ServicioController($c->get(ServicioService::class)));

        $c->singleton(
            FactorComplejidadRepository::class,
            fn () => new FactorComplejidadRepository($c->get(PDO::class)),
        );
        $c->singleton(
            FactorComplejidadService::class,
            fn () => new FactorComplejidadService($c->get(FactorComplejidadRepository::class)),
        );
        $c->singleton(
            FactorComplejidadController::class,
            fn () => new FactorComplejidadController($c->get(FactorComplejidadService::class)),
        );

        $c->singleton(HonorarioCalculator::class, fn () => new HonorarioCalculator());
        $c->singleton(
            HonorarioRepository::class,
            fn () => new HonorarioRepository($c->get(PDO::class)),
        );
        $c->singleton(HonorarioService::class, fn () => new HonorarioService(
            $c->get(HonorarioRepository::class),
            $c->get(ServicioRepository::class),
            $c->get(FactorComplejidadRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(HonorarioCalculator::class),
            $c->get(DB::class),
        ));
        $c->singleton(HonorarioController::class, fn () => new HonorarioController($c->get(HonorarioService::class)));
    }
}
