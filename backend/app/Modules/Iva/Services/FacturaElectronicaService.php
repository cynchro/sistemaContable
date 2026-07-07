<?php

namespace App\Modules\Iva\Services;

use App\Support\DB;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Compartido\Repositories\PeriodoRepository;
use App\Modules\Iva\Repositories\VentaRepository;
use App\Modules\Iva\Afip\Wsfe\WsfeClient;
use App\Modules\Iva\Afip\Wsfe\WsfeCatalogoRepository;
use App\Modules\Iva\Afip\Wsfe\WsfeComprobanteMapper;
use App\Modules\Iva\Afip\Wsfe\FacturaContexto;
use App\Modules\Iva\Afip\Wsfe\CbteTipoResolver;

/**
 * Emisión de factura electrónica (WSFEv1) para un comprobante de venta ya cargado:
 * resuelve el CbteTipo, pide a AFIP el último número autorizado (numeración por punto
 * de venta), arma el FeCAEReq, solicita el CAE y persiste el resultado en la cabecera.
 */
class FacturaElectronicaService
{
    public function __construct(
        private VentaRepository $ventas,
        private EmpresaRepository $empresas,
        private PeriodoRepository $periodos,
        private WsfeCatalogoRepository $catalogos,
        private WsfeClient $wsfe,
        private WsfeComprobanteMapper $mapper,
        private DB $db,
    ) {
    }

    /**
     * @return array<string, mixed> ['venta'=>..., 'cae'=>..., 'cae_vto'=>..., 'numero'=>...]
     */
    public function autorizar(int $empresaId, int $periodoId, int $ventaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);
        $this->periodos->findById($periodoId, $empresaId);

        $venta = $this->ventas->findById($ventaId, $periodoId);

        if (!empty($venta['cae'])) {
            throw new ConflictException('El comprobante ya tiene CAE.');
        }

        $ptoVta = (int) ($venta['punto_venta'] ?? 0);
        $letra  = (string) ($venta['letra'] ?? '');

        if ($ptoVta <= 0) {
            throw new ValidationException(['punto_venta' => ['El comprobante no tiene punto de venta.']]);
        }
        if ($letra === '') {
            throw new ValidationException(['letra' => ['El comprobante no tiene letra (A/B/C/M).']]);
        }

        $codigos  = $this->catalogos->codigosDeVenta($ventaId);
        $cbteTipo = CbteTipoResolver::resolve((string) $codigos['tipo_codigo'], $letra);

        // La numeración (último autorizado + 1) → solicitud de CAE → persistencia debe
        // correr una sola a la vez por punto de venta + tipo: dos emisiones concurrentes
        // tomarían el mismo número. Lock consultivo por PV+tipo del emisor. El timeout es
        // holgado porque adentro hay un round-trip SOAP a AFIP.
        return $this->db->withLock(
            "cae:{$empresaId}:{$ptoVta}:{$cbteTipo}",
            function () use ($ventaId, $periodoId, $venta, $codigos, $ptoVta, $cbteTipo) {
                $numero = $this->wsfe->ultimoAutorizado($ptoVta, $cbteTipo) + 1;

                $ctx = new FacturaContexto(
                    ptoVta: $ptoVta,
                    numero: $numero,
                    cbteTipo: $cbteTipo,
                    docTipo: (int) ($codigos['doc_tipo'] ?? 0),
                    condicionCodigo: (string) ($codigos['condicion_codigo'] ?? ''),
                    monId: ($codigos['moneda'] ?? '') !== '' ? (string) $codigos['moneda'] : 'PES',
                    monCotiz: (float) ($venta['tipo_cambio'] ?? 0) ?: 1.0,
                    cbtesAsoc: $this->cbtesAsoc((array) ($venta['comprobantes_asociados'] ?? [])),
                );

                $cae = $this->wsfe->solicitarCae($this->mapper->build($venta, $ctx));

                if (!$cae->aprobado()) {
                    $this->ventas->updateCae($ventaId, $periodoId, [
                        'afip_resultado' => $cae->resultado,
                        'afip_obs'       => json_encode([
                            'observaciones' => $cae->observaciones,
                            'errores'       => $cae->errores,
                        ]),
                    ]);

                    throw new ConflictException(
                        'AFIP rechazó el comprobante: ' . $this->mensajes($cae->errores, $cae->observaciones)
                    );
                }

                $obs = $cae->observaciones === [] ? null : json_encode(['observaciones' => $cae->observaciones]);

                $this->db->withTransaction(fn () => $this->ventas->updateCae($ventaId, $periodoId, [
                    'numero'         => str_pad((string) $numero, 8, '0', STR_PAD_LEFT),
                    'cae'            => $cae->cae,
                    'cae_vto'        => self::fechaIso((string) $cae->caeVto),
                    'afip_resultado' => 'A',
                    'afip_obs'       => $obs,
                ]));

                return [
                    'venta'   => $this->ventas->findById($ventaId, $periodoId),
                    'numero'  => $numero,
                    'cae'     => $cae->cae,
                    'cae_vto' => self::fechaIso((string) $cae->caeVto),
                ];
            },
            30,
        );
    }

    /**
     * Resuelve los comprobantes asociados de la venta al formato CbteAsoc de AFIP
     * (Tipo/PtoVta/Nro [+ Cuit/CbteFch]). El CbteTipo sale del resolver (tipo+letra).
     *
     * @param  list<array<string, mixed>> $asociados
     * @return list<array<string, mixed>>
     */
    private function cbtesAsoc(array $asociados): array
    {
        $out = [];
        foreach ($asociados as $a) {
            $cbteAsoc = [
                'Tipo'   => CbteTipoResolver::resolve((string) ($a['tipo_codigo'] ?? ''), (string) ($a['letra'] ?? '')),
                'PtoVta' => (int) ($a['punto_venta'] ?? 0),
                'Nro'    => (int) ($a['numero'] ?? 0),
            ];

            $cuit = preg_replace('/\D/', '', (string) ($a['cuit'] ?? '')) ?? '';
            if ($cuit !== '') {
                $cbteAsoc['Cuit'] = $cuit;
            }
            if (!empty($a['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $a['fecha'])) {
                $cbteAsoc['CbteFch'] = str_replace('-', '', substr((string) $a['fecha'], 0, 10));
            }

            $out[] = $cbteAsoc;
        }

        return $out;
    }

    /**
     * @param list<array{code:int,msg:string}> $errores
     * @param list<array{code:int,msg:string}> $observaciones
     */
    private function mensajes(array $errores, array $observaciones): string
    {
        $msgs = array_map(static fn (array $m) => "[{$m['code']}] {$m['msg']}", array_merge($errores, $observaciones));

        return $msgs === [] ? 'sin detalle' : implode(' · ', $msgs);
    }

    /** 'Ymd' de AFIP → 'Y-m-d' para la columna DATE. */
    private static function fechaIso(string $ymd): ?string
    {
        if (!preg_match('/^\d{8}$/', $ymd)) {
            return null;
        }

        return substr($ymd, 0, 4) . '-' . substr($ymd, 4, 2) . '-' . substr($ymd, 6, 2);
    }
}
