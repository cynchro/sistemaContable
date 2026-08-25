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
use App\Modules\Iva\Services\AuditoriaAfipService;
use App\Modules\Iva\Controllers\AuditoriaAfipController;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Compartido\Repositories\TipoRetencionRepository;
use App\Modules\Iva\Calc\IvaComprobanteCalculator;
use App\Modules\Iva\Calc\PercepcionCalculator;
use App\Modules\Iva\Calc\LibroIvaCalculator;
use App\Modules\Iva\Calc\AlertaEstadisticaCalculator;
use App\Modules\Iva\Services\AlertaEstadisticaService;
use App\Modules\Iva\Controllers\AlertaEstadisticaController;
use App\Modules\Iva\Calc\LibroIvaDetalleCalculator;
use App\Modules\Iva\Calc\DeclaracionIvaCalculator;
use App\Modules\Iva\Calc\IvaSimpleCalculator;
use App\Modules\Iva\Repositories\SujetoRepository;
use App\Modules\Iva\Repositories\SujetoEmpresaRepository;
use App\Modules\Iva\Repositories\ImputacionContableRepository;
use App\Modules\Iva\Repositories\VentaClasificacionRepository;
use App\Modules\Iva\Services\VentaClasificacionService;
use App\Modules\Iva\Controllers\VentaClasificacionController;
use App\Modules\Iva\Services\ImputacionContableService;
use App\Modules\Iva\Controllers\ImputacionContableController;
use App\Modules\Iva\Repositories\VentaRepository;
use App\Modules\Iva\Repositories\CompraRepository;
use App\Modules\Iva\Repositories\LibroIvaRepository;
use App\Modules\Iva\Repositories\DdjjSimpleRepository;
use App\Modules\Iva\Repositories\ReporteIvaRepository;
use App\Modules\Iva\Repositories\LibroIvaDigitalRepository;
use App\Modules\Iva\Repositories\ExportFormatoRepository;
use App\Modules\Iva\Export\LibroIvaDigitalWriter;
use App\Modules\Iva\Export\ExportTxtConfigurableWriter;
use App\Modules\Iva\Services\ExportFormatoService;
use App\Modules\Iva\Controllers\ExportFormatoController;
use App\Modules\Iva\Services\LibroIvaDigitalService;
use App\Modules\Iva\Controllers\LibroIvaDigitalController;
use App\Modules\Iva\Repositories\DjIvaSimpleRepository;
use App\Modules\Iva\Export\DjIvaSimpleWriter;
use App\Modules\Iva\Services\DjIvaSimpleService;
use App\Modules\Iva\Controllers\DjIvaSimpleController;
use App\Modules\Iva\Repositories\SifereRepository;
use App\Modules\Iva\Export\SifereWriter;
use App\Modules\Iva\Services\SifereService;
use App\Modules\Iva\Controllers\SifereController;
use App\Modules\Iva\Repositories\MayorRepository;
use App\Modules\Iva\Services\MayorService;
use App\Modules\Iva\Controllers\MayorController;
use App\Modules\Iva\Repositories\ReporteMayorRepository;
use App\Modules\Iva\Calc\MayorReporteCalculator;
use App\Modules\Iva\Services\ReporteMayorService;
use App\Modules\Iva\Repositories\AuditoriaRepository;
use App\Modules\Iva\Controllers\AuditoriaController;
use App\Modules\Iva\Audit\AuditMiddleware;
use App\Modules\Iva\Repositories\EmpresaActividadRepository;
use App\Modules\Iva\Services\EmpresaActividadService;
use App\Modules\Iva\Controllers\EmpresaActividadController;
use App\Modules\Iva\Services\SujetoService;
use App\Modules\Iva\Services\VentaService;
use App\Modules\Iva\Services\CompraService;
use App\Modules\Iva\Services\LibroIvaService;
use App\Modules\Iva\Services\ReporteIvaService;
use App\Modules\Iva\Controllers\IvaClienteController;
use App\Modules\Iva\Controllers\IvaProveedorController;
use App\Modules\Iva\Controllers\PadronUnicoController;
use App\Modules\Iva\Repositories\ConceptoRepository;
use App\Modules\Iva\Services\ConceptoService;
use App\Modules\Iva\Controllers\ConceptoController;
use App\Modules\Iva\Controllers\VentaController;
use App\Modules\Iva\Controllers\CompraController;
use App\Modules\Iva\Controllers\LibroIvaController;
use App\Modules\Iva\Controllers\ReporteIvaController;
use App\Modules\Iva\Repositories\LiquidacionRepository;
use App\Modules\Iva\Services\LiquidacionService;
use App\Modules\Iva\Controllers\LiquidacionController;
use App\Modules\Contribuyentes\Services\CredencialService;

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

        // Padrón Único de Sujetos IVA (clientes/proveedores comparten identidad; el rol
        // 'cliente'/'proveedor' lo fija cada Controller al llamar al mismo Service).
        $c->singleton(SujetoRepository::class, fn () => new SujetoRepository($c->get(PDO::class)));
        $c->singleton(SujetoEmpresaRepository::class, fn () => new SujetoEmpresaRepository($c->get(PDO::class)));
        // Catálogo de conceptos (documento "Satélite Visual IVA" §5.2/§5.4, migración 0051):
        // nivel intermedio tenant-level entre la regla de imputación (global) y la cuenta real
        // (por-empresa) — ver ImputacionContableRepository para la cadena de resolución completa.
        $c->singleton(ConceptoRepository::class, fn () => new ConceptoRepository($c->get(PDO::class)));
        $c->singleton(ConceptoService::class, fn () => new ConceptoService($c->get(ConceptoRepository::class)));
        $c->singleton(ConceptoController::class, fn () => new ConceptoController($c->get(ConceptoService::class)));
        // Imputación contable del padrón (documento "Satélite Visual IVA" §5): concepto por
        // defecto (Pantalla A) + regla por punto de venta (Pantalla B). Conectada a
        // CompraService desde la Parte 2 — ver documentacion/analisis-satelite-visual-iva.md §10.
        $c->singleton(
            ImputacionContableRepository::class,
            fn () => new ImputacionContableRepository($c->get(PDO::class)),
        );
        // Motor de clasificación de ventas por PV+tipo de comprobante (documento "Satélite Visual
        // IVA" §4) — mismo patrón, sin capa de sujeto (el PV es del propio contribuyente).
        $c->singleton(
            VentaClasificacionRepository::class,
            fn () => new VentaClasificacionRepository($c->get(PDO::class)),
        );
        $c->singleton(SujetoService::class, fn () => new SujetoService(
            $c->get(SujetoRepository::class),
            $c->get(SujetoEmpresaRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(ReferenceValidator::class),
            $c->get(DB::class),
        ));
        $c->singleton(
            IvaClienteController::class,
            fn () => new IvaClienteController($c->get(SujetoService::class)),
        );
        $c->singleton(
            IvaProveedorController::class,
            fn () => new IvaProveedorController($c->get(SujetoService::class)),
        );
        // Vista global del Padrón Único (documento "Satélite Visual IVA" §10, Etapa 4).
        $c->singleton(
            PadronUnicoController::class,
            fn () => new PadronUnicoController($c->get(SujetoService::class)),
        );
        // Pantalla B (página aparte, decisión B2): capa HTTP para administrar las reglas de
        // imputación por punto de venta del proveedor.
        $c->singleton(ImputacionContableService::class, fn () => new ImputacionContableService(
            $c->get(ImputacionContableRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(SujetoRepository::class),
            $c->get(SujetoEmpresaRepository::class),
            $c->get(ReferenceValidator::class),
        ));
        $c->singleton(
            ImputacionContableController::class,
            fn () => new ImputacionContableController($c->get(ImputacionContableService::class)),
        );

        // Motor de cálculos del módulo (calculadoras puras, sin estado).
        $c->singleton(IvaComprobanteCalculator::class, fn () => new IvaComprobanteCalculator());
        $c->singleton(PercepcionCalculator::class, fn () => new PercepcionCalculator());

        // Comprobantes de venta (agregado transaccional).
        $c->singleton(VentaRepository::class, fn () => new VentaRepository($c->get(PDO::class)));
        $c->singleton(VentaService::class, fn () => new VentaService(
            $c->get(VentaRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
            $c->get(IvaComprobanteCalculator::class),
            $c->get(DB::class),
            $c->get(ReferenceValidator::class),
            $c->get(PercepcionCalculator::class),
            $c->get(TipoRetencionRepository::class),
            $c->get(SujetoEmpresaRepository::class),
            $c->get(SujetoRepository::class),
            $c->get(VentaClasificacionRepository::class),
        ));
        $c->singleton(VentaController::class, fn () => new VentaController($c->get(VentaService::class)));

        // Comprobantes de compra (agregado transaccional; reusa las calculadoras).
        $c->singleton(CompraRepository::class, fn () => new CompraRepository($c->get(PDO::class)));
        $c->singleton(CompraService::class, fn () => new CompraService(
            $c->get(CompraRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
            $c->get(IvaComprobanteCalculator::class),
            $c->get(DB::class),
            $c->get(ReferenceValidator::class),
            $c->get(PercepcionCalculator::class),
            $c->get(TipoRetencionRepository::class),
            $c->get(SujetoEmpresaRepository::class),
            $c->get(SujetoRepository::class),
            $c->get(ImputacionContableRepository::class),
        ));
        $c->singleton(CompraController::class, fn () => new CompraController($c->get(CompraService::class)));

        // Libro IVA: totales del período derivados (motor + agregación SQL por signo).
        $c->singleton(LibroIvaCalculator::class, fn () => new LibroIvaCalculator());
        $c->singleton(LibroIvaDetalleCalculator::class, fn () => new LibroIvaDetalleCalculator());
        $c->singleton(DeclaracionIvaCalculator::class, fn () => new DeclaracionIvaCalculator());
        $c->singleton(IvaSimpleCalculator::class, fn () => new IvaSimpleCalculator());
        $c->singleton(LibroIvaRepository::class, fn () => new LibroIvaRepository($c->get(PDO::class)));
        $c->singleton(DdjjSimpleRepository::class, fn () => new DdjjSimpleRepository($c->get(PDO::class)));
        $c->singleton(LibroIvaService::class, fn () => new LibroIvaService(
            $c->get(LibroIvaRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
            $c->get(LibroIvaCalculator::class),
            $c->get(LibroIvaDetalleCalculator::class),
            $c->get(DeclaracionIvaCalculator::class),
            $c->get(IvaSimpleCalculator::class),
            $c->get(DdjjSimpleRepository::class),
        ));
        $c->singleton(LibroIvaController::class, fn () => new LibroIvaController($c->get(LibroIvaService::class)));

        // Motor de alertas estadísticas v1 (documento "Satélite Visual IVA" §7): reusa los
        // totales de período del Libro IVA, no agrega tablas ni columnas propias.
        $c->singleton(AlertaEstadisticaCalculator::class, fn () => new AlertaEstadisticaCalculator());
        $c->singleton(AlertaEstadisticaService::class, fn () => new AlertaEstadisticaService(
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
            $c->get(LibroIvaRepository::class),
            $c->get(LibroIvaCalculator::class),
            $c->get(AlertaEstadisticaCalculator::class),
        ));
        $c->singleton(
            AlertaEstadisticaController::class,
            fn () => new AlertaEstadisticaController($c->get(AlertaEstadisticaService::class)),
        );

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

        // Exportador TXT configurable: formatos definidos por el tenant (campos/orden/formato).
        $c->singleton(ExportTxtConfigurableWriter::class, fn () => new ExportTxtConfigurableWriter());
        $c->singleton(ExportFormatoRepository::class, fn () => new ExportFormatoRepository($c->get(PDO::class)));
        $c->singleton(ExportFormatoService::class, fn () => new ExportFormatoService(
            $c->get(ExportFormatoRepository::class),
            $c->get(ReporteIvaRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
            $c->get(ExportTxtConfigurableWriter::class),
            $c->get(DB::class),
        ));
        $c->singleton(
            ExportFormatoController::class,
            fn () => new ExportFormatoController($c->get(ExportFormatoService::class)),
        );

        // Libro IVA Digital (Portal IVA): exportación de los archivos de ancho fijo de ARCA.
        $c->singleton(LibroIvaDigitalRepository::class, fn () => new LibroIvaDigitalRepository($c->get(PDO::class)));
        $c->singleton(LibroIvaDigitalWriter::class, fn () => new LibroIvaDigitalWriter());
        $c->singleton(LibroIvaDigitalService::class, fn () => new LibroIvaDigitalService(
            $c->get(LibroIvaDigitalRepository::class),
            $c->get(LibroIvaDigitalWriter::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
        ));
        $c->singleton(
            LibroIvaDigitalController::class,
            fn () => new LibroIvaDigitalController($c->get(LibroIvaDigitalService::class)),
        );

        // DJ IVA Simple (Portal IVA): apertura de otros conceptos por actividad (4 CSV).
        $c->singleton(DjIvaSimpleRepository::class, fn () => new DjIvaSimpleRepository($c->get(PDO::class)));
        $c->singleton(DjIvaSimpleWriter::class, fn () => new DjIvaSimpleWriter());
        $c->singleton(DjIvaSimpleService::class, fn () => new DjIvaSimpleService(
            $c->get(DjIvaSimpleRepository::class),
            $c->get(DjIvaSimpleWriter::class),
            $c->get(EmpresaActividadRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
        ));
        $c->singleton(
            DjIvaSimpleController::class,
            fn () => new DjIvaSimpleController($c->get(DjIvaSimpleService::class)),
        );

        // SIFERE Convenio Multilateral V4: percepciones de IIBB sufridas por jurisdicción.
        $c->singleton(SifereRepository::class, fn () => new SifereRepository($c->get(PDO::class)));
        $c->singleton(SifereWriter::class, fn () => new SifereWriter());
        $c->singleton(SifereService::class, fn () => new SifereService(
            $c->get(SifereRepository::class),
            $c->get(SifereWriter::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
        ));
        $c->singleton(
            SifereController::class,
            fn () => new SifereController($c->get(SifereService::class)),
        );

        // Mayor de cuentas (mayorización interna): resumen + detalle por cuenta.
        $c->singleton(MayorRepository::class, fn () => new MayorRepository($c->get(PDO::class)));
        $c->singleton(MayorService::class, fn () => new MayorService(
            $c->get(MayorRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
        ));
        // Reporte analítico del Mayor por rango de fechas (R2): filtros + cascada con subtotales.
        $c->singleton(MayorReporteCalculator::class, fn () => new MayorReporteCalculator());
        $c->singleton(ReporteMayorRepository::class, fn () => new ReporteMayorRepository($c->get(PDO::class)));
        $c->singleton(ReporteMayorService::class, fn () => new ReporteMayorService(
            $c->get(ReporteMayorRepository::class),
            $c->get(MayorReporteCalculator::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(
            MayorController::class,
            fn () => new MayorController($c->get(MayorService::class), $c->get(ReporteMayorService::class)),
        );

        // Actividades por empresa (NAES) + mapa de puntos de venta (apertura DJ por actividad).
        $c->singleton(EmpresaActividadRepository::class, fn () => new EmpresaActividadRepository($c->get(PDO::class)));
        $c->singleton(EmpresaActividadService::class, fn () => new EmpresaActividadService(
            $c->get(EmpresaActividadRepository::class),
            $c->get(EmpresaRepository::class),
        ));
        $c->singleton(
            EmpresaActividadController::class,
            fn () => new EmpresaActividadController($c->get(EmpresaActividadService::class)),
        );

        // Clasificación de ventas por PV+tipo de comprobante (Pantalla D). El repositorio ya
        // está registrado más arriba (Parte 5); acá solo falta Service+Controller.
        $c->singleton(VentaClasificacionService::class, fn () => new VentaClasificacionService(
            $c->get(VentaClasificacionRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(ReferenceValidator::class),
        ));
        $c->singleton(
            VentaClasificacionController::class,
            fn () => new VentaClasificacionController($c->get(VentaClasificacionService::class)),
        );

        // Auditoría de operaciones (registro de cambios) — middleware + lectura paginada.
        $c->singleton(AuditoriaRepository::class, fn () => new AuditoriaRepository($c->get(PDO::class)));
        $c->singleton(AuditMiddleware::class, fn () => new AuditMiddleware($c->get(AuditoriaRepository::class)));
        $c->singleton(
            AuditoriaController::class,
            fn () => new AuditoriaController($c->get(AuditoriaRepository::class)),
        );

        // AFIP / WSAA: autenticación con certificado (TA cacheado en DB).
        $c->singleton(SoapTransport::class, fn () => new ExtSoapTransport());
        $c->singleton(TicketStore::class, fn () => new DbTicketStore($c->get(PDO::class)));
        $c->singleton(WsaaClient::class, fn () => new WsaaClient(
            new FileCmsSigner(
                Config::get('afip.cert_path'),
                Config::get('afip.key_path'),
                (string) Config::get('afip.key_passphrase', ''),
                Config::get('afip.cert_pem'),
                Config::get('afip.key_pem'),
            ),
            $c->get(SoapTransport::class),
            $c->get(TicketStore::class),
            (string) Config::get('afip.wsaa.' . Config::get('afip.env', 'homologacion')),
            (string) Config::get('afip.cuit', ''),
            (int) Config::get('afip.ta_margin', 600),
        ));

        // Padrón AFIP (consulta por CUIT). Usa su PROPIO WSAA (certificado + ambiente de
        // padrón), que puede diferir del de WSFE: p. ej. padrón en producción y WSFE en
        // homologación. El alcance (a13 por defecto / a5) define el WSDL y el WS.
        $alcancePadron = (string) Config::get('afip.padron_alcance', 'a13');
        $envPadron     = (string) Config::get('afip.padron_env', 'homologacion');
        $c->singleton(PadronClient::class, fn () => new AfipPadronClient(
            new WsaaClient(
                new FileCmsSigner(
                    Config::get('afip.padron_cert_path'),
                    Config::get('afip.padron_key_path'),
                    (string) Config::get('afip.padron_key_passphrase', ''),
                    Config::get('afip.padron_cert_pem'),
                    Config::get('afip.padron_key_pem'),
                ),
                $c->get(SoapTransport::class),
                $c->get(TicketStore::class),
                (string) Config::get('afip.wsaa.' . $envPadron),
                (string) Config::get('afip.cuit', ''),
                (int) Config::get('afip.ta_margin', 600),
            ),
            // El WSDL del padrón (A5/A13) declara binding SOAP 1.1; el transporte por defecto
            // usa 1.2 (para WSFE). Un transporte 1.1 dedicado evita el fault vacío de getPersona.
            new ExtSoapTransport(['soap_version' => SOAP_1_1]),
            (string) Config::get("afip.padron.{$alcancePadron}.{$envPadron}"),
            (string) Config::get('afip.cuit', ''),
            'ws_sr_padron_' . $alcancePadron,
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

        // Auditoría de ventas vs. ARCA: reusa VentaRepository/EmpresaRepository/WsfeClient
        // ya registrados arriba, sin estado propio.
        $c->singleton(AuditoriaAfipService::class, fn () => new AuditoriaAfipService(
            $c->get(VentaRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(WsfeClient::class),
        ));
        $c->singleton(
            AuditoriaAfipController::class,
            fn () => new AuditoriaAfipController($c->get(AuditoriaAfipService::class)),
        );

        // Botón "Liquidar IVA" (plan 25/08/2026): cola de pedidos + endpoints de usuario y del
        // worker externo del bot. Reusa CredencialService (módulo Contribuyentes) para descifrar
        // la Clave Fiscal internamente, sin pasar por su Controller/Response pública.
        $c->singleton(LiquidacionRepository::class, fn () => new LiquidacionRepository($c->get(PDO::class)));
        $c->singleton(LiquidacionService::class, fn () => new LiquidacionService(
            $c->get(LiquidacionRepository::class),
            $c->get(EmpresaRepository::class),
            $c->get(PeriodoRepository::class),
            $c->get(CredencialService::class),
        ));
        $c->singleton(
            LiquidacionController::class,
            fn () => new LiquidacionController($c->get(LiquidacionService::class)),
        );
    }
}
