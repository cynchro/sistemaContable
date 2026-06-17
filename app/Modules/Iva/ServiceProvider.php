<?php

namespace App\Modules\Iva;

use PDO;
use App\Support\DB;
use App\Support\Config;
use App\Support\ReferenceValidator;
use App\Support\ServiceProvider as BaseServiceProvider;
use App\Modules\Iva\Afip\Soap\SoapTransport;
use App\Modules\Iva\Afip\Soap\ExtSoapTransport;
use App\Modules\Iva\Afip\Wsaa\TicketStore;
use App\Modules\Iva\Afip\Wsaa\DbTicketStore;
use App\Modules\Iva\Afip\Wsaa\WsaaClient;
use App\Modules\Iva\Afip\Wsaa\FileCmsSigner;
use App\Modules\Iva\Afip\Padron\PadronClient;
use App\Modules\Iva\Afip\Padron\AfipPadronClient;
use App\Modules\Iva\Services\PadronService;
use App\Modules\Iva\Controllers\PadronController;
use App\Modules\Iva\Afip\Wsfe\WsfeClient;
use App\Modules\Iva\Afip\Wsfe\AfipWsfeClient;
use App\Modules\Iva\Afip\Wsfe\WsfeComprobanteMapper;
use App\Modules\Iva\Afip\Wsfe\WsfeCatalogoRepository;
use App\Modules\Iva\Services\FacturaElectronicaService;
use App\Modules\Iva\Controllers\FacturaElectronicaController;
use App\Modules\Iva\Repositories\PuntoVentaRepository;
use App\Modules\Iva\Services\PuntoVentaService;
use App\Modules\Iva\Controllers\PuntoVentaController;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Calc\IvaComprobanteCalculator;
use App\Modules\Iva\Calc\LibroIvaCalculator;
use App\Modules\Iva\Calc\LibroIvaDetalleCalculator;
use App\Modules\Iva\Calc\DeclaracionIvaCalculator;
use App\Modules\Iva\Repositories\IvaClienteRepository;
use App\Modules\Iva\Repositories\IvaProveedorRepository;
use App\Modules\Iva\Repositories\VentaRepository;
use App\Modules\Iva\Repositories\CompraRepository;
use App\Modules\Iva\Repositories\LibroIvaRepository;
use App\Modules\Iva\Repositories\ReporteIvaRepository;
use App\Modules\Iva\Services\IvaClienteService;
use App\Modules\Iva\Services\IvaProveedorService;
use App\Modules\Iva\Services\VentaService;
use App\Modules\Iva\Services\CompraService;
use App\Modules\Iva\Services\LibroIvaService;
use App\Modules\Iva\Services\ReporteIvaService;
use App\Modules\Iva\Controllers\IvaClienteController;
use App\Modules\Iva\Controllers\IvaProveedorController;
use App\Modules\Iva\Controllers\VentaController;
use App\Modules\Iva\Controllers\CompraController;
use App\Modules\Iva\Controllers\LibroIvaController;
use App\Modules\Iva\Controllers\ReporteIvaController;

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

        $c->singleton(ReferenceValidator::class, fn () => new ReferenceValidator($c->get(PDO::class)));

        $c->singleton(IvaClienteRepository::class, fn () => new IvaClienteRepository($c->get(PDO::class)));
        $c->singleton(IvaClienteService::class, fn () => new IvaClienteService(
            $c->get(IvaClienteRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(ReferenceValidator::class),
        ));
        $c->singleton(
            IvaClienteController::class,
            fn () => new IvaClienteController($c->get(IvaClienteService::class)),
        );

        $c->singleton(IvaProveedorRepository::class, fn () => new IvaProveedorRepository($c->get(PDO::class)));
        $c->singleton(IvaProveedorService::class, fn () => new IvaProveedorService(
            $c->get(IvaProveedorRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(ReferenceValidator::class),
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

        // Comprobantes de compra (agregado transaccional; reusa la calculadora).
        $c->singleton(CompraRepository::class, fn () => new CompraRepository($c->get(PDO::class)));
        $c->singleton(CompraService::class, fn () => new CompraService(
            $c->get(CompraRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
            $c->get(IvaComprobanteCalculator::class),
            $c->get(DB::class),
        ));
        $c->singleton(CompraController::class, fn () => new CompraController($c->get(CompraService::class)));

        // Libro IVA: totales del período derivados (motor + agregación SQL por signo).
        $c->singleton(LibroIvaCalculator::class, fn () => new LibroIvaCalculator());
        $c->singleton(LibroIvaDetalleCalculator::class, fn () => new LibroIvaDetalleCalculator());
        $c->singleton(DeclaracionIvaCalculator::class, fn () => new DeclaracionIvaCalculator());
        $c->singleton(LibroIvaRepository::class, fn () => new LibroIvaRepository($c->get(PDO::class)));
        $c->singleton(LibroIvaService::class, fn () => new LibroIvaService(
            $c->get(LibroIvaRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
            $c->get(LibroIvaCalculator::class),
            $c->get(LibroIvaDetalleCalculator::class),
            $c->get(DeclaracionIvaCalculator::class),
        ));
        $c->singleton(LibroIvaController::class, fn () => new LibroIvaController($c->get(LibroIvaService::class)));

        // Reportes (subdiario / libro IVA): listado de comprobantes enriquecido + totales.
        $c->singleton(ReporteIvaRepository::class, fn () => new ReporteIvaRepository($c->get(PDO::class)));
        $c->singleton(ReporteIvaService::class, fn () => new ReporteIvaService(
            $c->get(ReporteIvaRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
        ));
        $c->singleton(
            ReporteIvaController::class,
            fn () => new ReporteIvaController($c->get(ReporteIvaService::class)),
        );

        // AFIP / WSAA: autenticación con certificado (TA cacheado en DB).
        $c->singleton(SoapTransport::class, fn () => new ExtSoapTransport());
        $c->singleton(TicketStore::class, fn () => new DbTicketStore($c->get(PDO::class)));
        $c->singleton(WsaaClient::class, fn () => new WsaaClient(
            new FileCmsSigner(
                Config::get('afip.cert_path'),
                Config::get('afip.key_path'),
                (string) Config::get('afip.key_passphrase', ''),
            ),
            $c->get(SoapTransport::class),
            $c->get(TicketStore::class),
            (string) Config::get('afip.wsaa.' . Config::get('afip.env', 'homologacion')),
            (string) Config::get('afip.cuit', ''),
            (int) Config::get('afip.ta_margin', 600),
        ));

        // Padrón AFIP (consulta por CUIT). Reusa el WSAA para autenticarse.
        $c->singleton(PadronClient::class, fn () => new AfipPadronClient(
            $c->get(WsaaClient::class),
            $c->get(SoapTransport::class),
            (string) Config::get('afip.padron_a5.' . Config::get('afip.env', 'homologacion')),
            (string) Config::get('afip.cuit', ''),
        ));
        $c->singleton(PadronService::class, fn () => new PadronService($c->get(PadronClient::class)));
        $c->singleton(PadronController::class, fn () => new PadronController($c->get(PadronService::class)));

        // Factura electrónica (WSFEv1): numeración por punto de venta + solicitud de CAE.
        $c->singleton(WsfeComprobanteMapper::class, fn () => new WsfeComprobanteMapper());
        $c->singleton(WsfeCatalogoRepository::class, fn () => new WsfeCatalogoRepository($c->get(PDO::class)));
        $c->singleton(WsfeClient::class, fn () => new AfipWsfeClient(
            $c->get(WsaaClient::class),
            $c->get(SoapTransport::class),
            (string) Config::get('afip.wsfe.' . Config::get('afip.env', 'homologacion')),
            (string) Config::get('afip.cuit', ''),
        ));
        $c->singleton(FacturaElectronicaService::class, fn () => new FacturaElectronicaService(
            $c->get(VentaRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
            $c->get(WsfeCatalogoRepository::class),
            $c->get(WsfeClient::class),
            $c->get(WsfeComprobanteMapper::class),
            $c->get(DB::class),
        ));
        $c->singleton(
            FacturaElectronicaController::class,
            fn () => new FacturaElectronicaController($c->get(FacturaElectronicaService::class)),
        );

        // ABM de puntos de venta (numeración de factura electrónica).
        $c->singleton(PuntoVentaRepository::class, fn () => new PuntoVentaRepository($c->get(PDO::class)));
        $c->singleton(PuntoVentaService::class, fn () => new PuntoVentaService(
            $c->get(PuntoVentaRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(
            PuntoVentaController::class,
            fn () => new PuntoVentaController($c->get(PuntoVentaService::class)),
        );
    }
}
