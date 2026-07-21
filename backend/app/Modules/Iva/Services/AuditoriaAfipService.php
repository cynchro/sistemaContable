<?php

namespace App\Modules\Iva\Services;

use App\Modules\Compartido\Repositories\EmpresaRepository;
use App\Modules\Iva\Repositories\VentaRepository;
use App\Modules\Iva\Afip\Wsfe\WsfeClient;
use App\Modules\Iva\Afip\Wsfe\CbteTipoResolver;

/**
 * Auditoría de ventas contra ARCA (WSFEv1): compara, por cada combinación punto de
 * venta + tipo + letra que la empresa ya usó localmente, el último número que ARCA
 * reconoce como autorizado contra el último número cargado en nuestra base. Detecta
 * así comprobantes emitidos en ARCA que no llegaron a cargarse acá (típicamente
 * cargados a mano sin pasar por el flujo de CAE, o directamente faltantes).
 *
 * Sin tabla propia: se calcula en vivo contra ARCA cada vez (misma convención que el
 * resto del proyecto — "totales derivados on-the-fly, sin columnas persistidas").
 * Solo compara combinaciones ya vistas localmente; si un punto de venta emitió en ARCA
 * un tipo de comprobante que acá nunca se cargó ni una vez, esta auditoría no lo detecta
 * (limitación conocida de la v1).
 */
class AuditoriaAfipService
{
    public function __construct(
        private VentaRepository $ventas,
        private EmpresaRepository $empresas,
        private WsfeClient $wsfe,
    ) {
    }

    /**
     * @return list<array{
     *     punto_venta:string, tipo_comprobante_id:int, tipo_comprobante:string, letra:string,
     *     ultimo_arca:int, ultimo_local:int, faltantes:int,
     * }>
     */
    public function resumen(int $empresaId, string $tenantId): array
    {
        $this->empresas->findById($empresaId, $tenantId);

        $filas = [];
        foreach ($this->ventas->tiposUsados($empresaId) as $combo) {
            $cbteTipo = CbteTipoResolver::resolve($combo['tipo_codigo'], $combo['letra']);
            $ultimoArca = $this->wsfe->ultimoAutorizado((int) $combo['punto_venta'], $cbteTipo);
            $ultimoLocal = $this->ventas->maxNumero(
                $empresaId,
                $combo['punto_venta'],
                $combo['tipo_comprobante_id'],
                $combo['letra'],
            );

            $filas[] = [
                'punto_venta'         => $combo['punto_venta'],
                'tipo_comprobante_id' => $combo['tipo_comprobante_id'],
                'tipo_comprobante'    => $combo['tipo_codigo'],
                'letra'               => $combo['letra'],
                'ultimo_arca'         => $ultimoArca,
                'ultimo_local'        => $ultimoLocal,
                'faltantes'           => max(0, $ultimoArca - $ultimoLocal),
            ];
        }

        return $filas;
    }

    /**
     * Detalle de un comprobante puntual según ARCA, más si ya está cargado localmente.
     *
     * @return array<string, mixed>
     */
    public function detalleComprobante(
        int $empresaId,
        int $tipoComprobanteId,
        string $puntoVenta,
        string $letra,
        string $numero,
        string $tenantId,
    ): array {
        $this->empresas->findById($empresaId, $tenantId);

        $tipoCodigo = $this->ventas->tipoComprobanteCodigo($tipoComprobanteId);
        $cbteTipo = CbteTipoResolver::resolve($tipoCodigo ?? '', $letra);

        $detalle = $this->wsfe->consultarComprobante((int) $puntoVenta, $cbteTipo, (int) $numero);
        $local = $this->ventas->findByComprobante($empresaId, $puntoVenta, $tipoComprobanteId, $letra, $numero);

        return [
            'encontrado'    => $detalle->encontrado,
            'resultado'     => $detalle->resultado,
            'fecha'         => $detalle->fecha,
            'total'         => $detalle->impTotal,
            'neto'          => $detalle->impNeto,
            'cae'           => $detalle->cae,
            'cae_vto'       => $detalle->caeVto,
            'alicuotas'     => $detalle->alicuotas,
            'ya_cargado'    => $local !== null,
            'venta_id_local' => $local['id'] ?? null,
        ];
    }
}
