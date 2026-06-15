<?php

namespace App\Modules\Iva;

use PDO;
use App\Support\DB;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Calc\IvaComprobanteCalculator;
use App\Modules\Iva\Repositories\IvaClienteRepository;
use App\Modules\Iva\Repositories\IvaProveedorRepository;
use App\Modules\Iva\Repositories\VentaRepository;
use App\Modules\Iva\Services\IvaClienteService;
use App\Modules\Iva\Services\IvaProveedorService;
use App\Modules\Iva\Services\VentaService;
use App\Modules\Iva\Controllers\IvaClienteController;
use App\Modules\Iva\Controllers\IvaProveedorController;
use App\Modules\Iva\Controllers\VentaController;

/**
 * Wiring del módulo Iva. Reusa EmpresaRepository del módulo Compartido para
 * validar la pertenencia de la empresa al tenant. Las rutas se autocargan en la
 * etapa 8 del bootstrap. Las calculadoras (motor de cálculos) se registrarán acá
 * cuando lleguen los comprobantes (ventas/compras).
 */
class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $c = $this->container;

        $c->singleton(IvaClienteRepository::class, fn () => new IvaClienteRepository($c->get(PDO::class)));
        $c->singleton(IvaClienteService::class, fn () => new IvaClienteService(
            $c->get(IvaClienteRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(
            IvaClienteController::class,
            fn () => new IvaClienteController($c->get(IvaClienteService::class)),
        );

        $c->singleton(IvaProveedorRepository::class, fn () => new IvaProveedorRepository($c->get(PDO::class)));
        $c->singleton(IvaProveedorService::class, fn () => new IvaProveedorService(
            $c->get(IvaProveedorRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(
            IvaProveedorController::class,
            fn () => new IvaProveedorController($c->get(IvaProveedorService::class)),
        );

        // Motor de cálculos del módulo (calculadora pura, sin estado).
        $c->singleton(IvaComprobanteCalculator::class, fn () => new IvaComprobanteCalculator());

        // Comprobantes de venta (agregado transaccional).
        $c->singleton(VentaRepository::class, fn () => new VentaRepository($c->get(PDO::class)));
        $c->singleton(VentaService::class, fn () => new VentaService(
            $c->get(VentaRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
            $c->get(IvaComprobanteCalculator::class),
            $c->get(DB::class),
        ));
        $c->singleton(VentaController::class, fn () => new VentaController($c->get(VentaService::class)));
    }
}
