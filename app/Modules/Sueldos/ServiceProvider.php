<?php

namespace App\Modules\Sueldos;

use PDO;
use App\Support\DB;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Sueldos\Calc\FormulaEvaluator;
use App\Modules\Sueldos\Calc\AntiguedadCalculator;
use App\Modules\Sueldos\Calc\LiquidacionCalculator;
use App\Modules\Sueldos\Calc\ContribucionCalculator;
use App\Modules\Sueldos\Calc\SacCalculator;
use App\Modules\Sueldos\Repositories\EmpleadoRepository;
use App\Modules\Sueldos\Repositories\FamiliarRepository;
use App\Modules\Sueldos\Repositories\EmpresaConfigRepository;
use App\Modules\Sueldos\Repositories\ConceptoRepository;
use App\Modules\Sueldos\Repositories\LiquidacionRepository;
use App\Modules\Sueldos\Repositories\ContribucionRepository;
use App\Modules\Sueldos\Services\EmpleadoService;
use App\Modules\Sueldos\Services\FamiliarService;
use App\Modules\Sueldos\Services\EmpresaConfigService;
use App\Modules\Sueldos\Services\ConceptoService;
use App\Modules\Sueldos\Services\LiquidacionService;
use App\Modules\Sueldos\Services\ContribucionService;
use App\Modules\Sueldos\Services\SacService;
use App\Modules\Sueldos\Controllers\EmpleadoController;
use App\Modules\Sueldos\Controllers\FamiliarController;
use App\Modules\Sueldos\Controllers\EmpresaConfigController;
use App\Modules\Sueldos\Controllers\ConceptoController;
use App\Modules\Sueldos\Controllers\LiquidacionController;
use App\Modules\Sueldos\Controllers\ContribucionController;
use App\Modules\Sueldos\Controllers\SacController;

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

        // Grupo familiar del empleado.
        $c->singleton(FamiliarRepository::class, fn () => new FamiliarRepository($c->get(PDO::class)));
        $c->singleton(FamiliarService::class, fn () => new FamiliarService(
            $c->get(FamiliarRepository::class),
            $c->get(EmpleadoRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(FamiliarController::class, fn () => new FamiliarController($c->get(FamiliarService::class)));

        // Configuración de sueldos por empresa (1:1).
        $c->singleton(EmpresaConfigRepository::class, fn () => new EmpresaConfigRepository($c->get(PDO::class)));
        $c->singleton(EmpresaConfigService::class, fn () => new EmpresaConfigService(
            $c->get(EmpresaConfigRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(
            EmpresaConfigController::class,
            fn () => new EmpresaConfigController($c->get(EmpresaConfigService::class)),
        );

        // Motor de fórmulas y calculadoras (núcleo del cálculo de liquidación).
        $c->singleton(FormulaEvaluator::class, fn () => new FormulaEvaluator());
        $c->singleton(AntiguedadCalculator::class, fn () => new AntiguedadCalculator());
        $c->singleton(ContribucionCalculator::class, fn () => new ContribucionCalculator());
        $c->singleton(
            LiquidacionCalculator::class,
            fn () => new LiquidacionCalculator($c->get(FormulaEvaluator::class)),
        );

        // Conceptos (haberes/descuentos con fórmula).
        $c->singleton(ConceptoRepository::class, fn () => new ConceptoRepository($c->get(PDO::class)));
        $c->singleton(ConceptoService::class, fn () => new ConceptoService(
            $c->get(ConceptoRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(ConceptoController::class, fn () => new ConceptoController($c->get(ConceptoService::class)));

        // Liquidaciones (corrida + novedades + recibos).
        $c->singleton(LiquidacionRepository::class, fn () => new LiquidacionRepository($c->get(PDO::class)));
        $c->singleton(LiquidacionService::class, fn () => new LiquidacionService(
            $c->get(LiquidacionRepository::class),
            $c->get(EmpleadoRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(AntiguedadCalculator::class),
            $c->get(LiquidacionCalculator::class),
            $c->get(DB::class),
        ));
        $c->singleton(
            LiquidacionController::class,
            fn () => new LiquidacionController($c->get(LiquidacionService::class)),
        );

        // SAC (aguinaldo): cálculo desde el historial de liquidaciones.
        $c->singleton(SacCalculator::class, fn () => new SacCalculator());
        $c->singleton(SacService::class, fn () => new SacService(
            $c->get(LiquidacionRepository::class),
            $c->get(EmpleadoRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(SacCalculator::class),
        ));
        $c->singleton(SacController::class, fn () => new SacController($c->get(SacService::class)));

        // Contribuciones patronales (definiciones + cálculo sobre la base del recibo).
        $c->singleton(ContribucionRepository::class, fn () => new ContribucionRepository($c->get(PDO::class)));
        $c->singleton(ContribucionService::class, fn () => new ContribucionService(
            $c->get(ContribucionRepository::class),
            $c->get(LiquidacionRepository::class),
            $c->get(EmpleadoRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(ContribucionCalculator::class),
            $c->get(DB::class),
        ));
        $c->singleton(
            ContribucionController::class,
            fn () => new ContribucionController($c->get(ContribucionService::class)),
        );
    }
}
