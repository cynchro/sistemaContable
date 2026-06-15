<?php

namespace App\Modules\Sueldos;

use PDO;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Sueldos\Calc\FormulaEvaluator;
use App\Modules\Sueldos\Repositories\EmpleadoRepository;
use App\Modules\Sueldos\Repositories\ConceptoRepository;
use App\Modules\Sueldos\Services\EmpleadoService;
use App\Modules\Sueldos\Services\ConceptoService;
use App\Modules\Sueldos\Controllers\EmpleadoController;
use App\Modules\Sueldos\Controllers\ConceptoController;

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

        // Motor de fórmulas (núcleo del cálculo de liquidación).
        $c->singleton(FormulaEvaluator::class, fn () => new FormulaEvaluator());

        // Conceptos (haberes/descuentos con fórmula).
        $c->singleton(ConceptoRepository::class, fn () => new ConceptoRepository($c->get(PDO::class)));
        $c->singleton(ConceptoService::class, fn () => new ConceptoService(
            $c->get(ConceptoRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(ConceptoController::class, fn () => new ConceptoController($c->get(ConceptoService::class)));
    }
}
