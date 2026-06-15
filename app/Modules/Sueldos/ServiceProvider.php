<?php

namespace App\Modules\Sueldos;

use PDO;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Sueldos\Repositories\EmpleadoRepository;
use App\Modules\Sueldos\Services\EmpleadoService;
use App\Modules\Sueldos\Controllers\EmpleadoController;

/**
 * Wiring del módulo Sueldos. Reusa EmpresaRepository del Compartido (empresa
 * canónica). Las rutas se autocargan en la etapa 8 del bootstrap. Las calculadoras
 * (FormulaEvaluator, LiquidacionCalculator) se registrarán al llegar la liquidación.
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $c = $this->container;

        $c->singleton(EmpleadoRepository::class, fn () => new EmpleadoRepository($c->get(PDO::class)));
        $c->singleton(EmpleadoService::class, fn () => new EmpleadoService(
            $c->get(EmpleadoRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(EmpleadoController::class, fn () => new EmpleadoController($c->get(EmpleadoService::class)));
    }
}
