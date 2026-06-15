<?php

namespace App\Modules\Compartido;

use PDO;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Compartido\Services\EmpresaService;
use App\Modules\Compartido\Services\PeriodoService;
use App\Modules\Compartido\Controllers\EmpresaController;
use App\Modules\Compartido\Controllers\PeriodoController;

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
    }
}
