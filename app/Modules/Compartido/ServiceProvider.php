<?php

namespace App\Modules\Compartido;

use PDO;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Compartido\Repositories\CuentaRepository;
use App\Modules\Compartido\Repositories\RubroRepository;
use App\Modules\Compartido\Repositories\CatalogoRepository;
use App\Modules\Compartido\Services\EmpresaService;
use App\Modules\Compartido\Services\PeriodoService;
use App\Modules\Compartido\Services\CuentaService;
use App\Modules\Compartido\Services\RubroService;
use App\Modules\Compartido\Controllers\EmpresaController;
use App\Modules\Compartido\Controllers\PeriodoController;
use App\Modules\Compartido\Controllers\CuentaController;
use App\Modules\Compartido\Controllers\RubroController;
use App\Modules\Compartido\Controllers\CatalogoController;

/**
 * Wiring del módulo Compartido. Registra bindings explícitos en el container
 * (sin depender de la autoresolución por reflexión). Las rutas se autocargan
 * desde routes.php en la etapa 8 del bootstrap.
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $c = $this->container;

        $c->singleton(EmpresaRepository::class, fn () => new EmpresaRepository($c->get(PDO::class)));
        $c->singleton(EmpresaService::class, fn () => new EmpresaService($c->get(EmpresaRepository::class)));
        $c->singleton(EmpresaController::class, fn () => new EmpresaController($c->get(EmpresaService::class)));

        $c->singleton(PeriodoRepository::class, fn () => new PeriodoRepository($c->get(PDO::class)));
        $c->singleton(PeriodoService::class, fn () => new PeriodoService(
            $c->get(PeriodoRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(PeriodoController::class, fn () => new PeriodoController($c->get(PeriodoService::class)));

        $c->singleton(CuentaRepository::class, fn () => new CuentaRepository($c->get(PDO::class)));
        $c->singleton(CuentaService::class, fn () => new CuentaService(
            $c->get(CuentaRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(CuentaController::class, fn () => new CuentaController($c->get(CuentaService::class)));

        $c->singleton(RubroRepository::class, fn () => new RubroRepository($c->get(PDO::class)));
        $c->singleton(RubroService::class, fn () => new RubroService($c->get(RubroRepository::class)));
        $c->singleton(RubroController::class, fn () => new RubroController($c->get(RubroService::class)));

        $c->singleton(CatalogoRepository::class, fn () => new CatalogoRepository($c->get(PDO::class)));
        $c->singleton(CatalogoController::class, fn () => new CatalogoController($c->get(CatalogoRepository::class)));
    }
}
