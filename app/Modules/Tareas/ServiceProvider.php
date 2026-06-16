<?php

namespace App\Modules\Tareas;

use PDO;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Tareas\Repositories\TareaTipoRepository;
use App\Modules\Tareas\Repositories\TareaRepository;
use App\Modules\Tareas\Services\TareaTipoService;
use App\Modules\Tareas\Services\TareaService;
use App\Modules\Tareas\Controllers\TareaTipoController;
use App\Modules\Tareas\Controllers\TareaController;

/**
 * Wiring del módulo Tareas (workflow del estudio, de sistemaCuarto). Tenant-scoped;
 * reusa EmpresaRepository para validar la empresa (contribuyente) opcional.
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $c = $this->container;

        $c->singleton(TareaTipoRepository::class, fn () => new TareaTipoRepository($c->get(PDO::class)));
        $c->singleton(TareaTipoService::class, fn () => new TareaTipoService($c->get(TareaTipoRepository::class)));
        $c->singleton(
            TareaTipoController::class,
            fn () => new TareaTipoController($c->get(TareaTipoService::class)),
        );

        $c->singleton(TareaRepository::class, fn () => new TareaRepository($c->get(PDO::class)));
        $c->singleton(TareaService::class, fn () => new TareaService(
            $c->get(TareaRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(TareaController::class, fn () => new TareaController($c->get(TareaService::class)));
    }
}
