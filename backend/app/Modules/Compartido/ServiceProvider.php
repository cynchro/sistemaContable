<?php

namespace App\Modules\Compartido;

use PDO;
use App\Support\DB;
use App\Support\Config;
use App\Support\ReferenceValidator;
use App\Support\Auth\PermissionChecker;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\EmpresaLockRepository;
use App\Modules\Compartido\Repositories\UsuarioEmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Compartido\Repositories\CuentaRepository;
use App\Modules\Compartido\Repositories\RubroRepository;
use App\Modules\Compartido\Repositories\TipoRetencionRepository;
use App\Modules\Compartido\Repositories\CatalogoRepository;
use App\Modules\Compartido\Services\EmpresaService;
use App\Modules\Compartido\Services\EmpresaLockService;
use App\Modules\Compartido\Services\PeriodoService;
use App\Modules\Compartido\Services\CuentaService;
use App\Modules\Compartido\Services\RubroService;
use App\Modules\Compartido\Services\TipoRetencionService;
use App\Modules\Compartido\Services\SigeService;
use App\Modules\Compartido\Controllers\EmpresaController;
use App\Modules\Compartido\Controllers\EmpresaLockController;
use App\Modules\Compartido\Controllers\PeriodoController;
use App\Modules\Compartido\Controllers\CuentaController;
use App\Modules\Compartido\Controllers\RubroController;
use App\Modules\Compartido\Controllers\TipoRetencionController;
use App\Modules\Compartido\Controllers\CatalogoController;
use App\Modules\Compartido\Controllers\SigeController;
use App\Modules\Compartido\Sige\SigeClient;
use App\Modules\Compartido\Sige\HttpSigeClient;

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

        $c->singleton(EmpresaLockRepository::class, fn () => new EmpresaLockRepository($c->get(PDO::class)));
        $c->singleton(EmpresaLockService::class, fn () => new EmpresaLockService(
            $c->get(EmpresaLockRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(EmpresaLockController::class, fn () => new EmpresaLockController(
            $c->get(EmpresaLockService::class),
            $c->get(PermissionChecker::class),
        ));
        $c->singleton(
            UsuarioEmpresaRepository::class,
            fn () => new UsuarioEmpresaRepository($c->get(PDO::class), $c->get(DB::class)),
        );
        $c->singleton(EmpresaService::class, fn () => new EmpresaService(
            $c->get(EmpresaRepository::class),
            $c->get(UsuarioEmpresaRepository::class),
        ));
        $c->singleton(EmpresaController::class, fn () => new EmpresaController(
            $c->get(EmpresaService::class),
            $c->get(PermissionChecker::class),
        ));

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

        $c->singleton(ReferenceValidator::class, fn () => new ReferenceValidator($c->get(PDO::class)));
        $c->singleton(TipoRetencionRepository::class, fn () => new TipoRetencionRepository($c->get(PDO::class)));
        $c->singleton(TipoRetencionService::class, fn () => new TipoRetencionService(
            $c->get(TipoRetencionRepository::class),
            $c->get(ReferenceValidator::class),
        ));
        $c->singleton(
            TipoRetencionController::class,
            fn () => new TipoRetencionController($c->get(TipoRetencionService::class)),
        );

        $c->singleton(CatalogoRepository::class, fn () => new CatalogoRepository($c->get(PDO::class)));
        $c->singleton(CatalogoController::class, fn () => new CatalogoController($c->get(CatalogoRepository::class)));

        $c->singleton(SigeClient::class, fn () => new HttpSigeClient(
            (string) Config::get('sige.base_url'),
            (string) Config::get('sige.api_key'),
            (int) Config::get('sige.timeout', 5),
        ));
        $c->singleton(SigeService::class, fn () => new SigeService($c->get(SigeClient::class)));
        $c->singleton(SigeController::class, fn () => new SigeController($c->get(SigeService::class)));
    }
}
