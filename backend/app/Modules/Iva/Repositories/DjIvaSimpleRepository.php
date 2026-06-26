<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Agregación de las operaciones del período para la DJ IVA Simple (apertura de otros
 * conceptos por actividad). Separa por signo del comprobante: signo > 0 alimenta
 * Débito/Crédito Fiscal; signo < 0 (notas de crédito) alimenta las Restituciones.
 *
 * La **actividad** de cada venta se resuelve con precedencia: actividad cargada en el
 * comprobante (override manual) → mapa {punto_venta → actividad} de la empresa (estrategia
 * más común) → null (el Service aplica la actividad por defecto de la empresa). Las ventas
 * llevan además `es_bien_uso`; las compras `concepto_dj`. Ver
 * docs/ingenieria-inversa/dj-iva-simple-actividad.md (v2).
 */
class DjIvaSimpleRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** SELECT del código de actividad resuelto (override del comprobante o mapa de PV). */
    private const ACTIVIDAD_RESUELTA = 'COALESCE(vea.codigo, pvea.codigo)';

    /** JOINs para resolver la actividad de una venta (alias de tabla `v`). */
    private const JOIN_ACTIVIDAD =
        'LEFT JOIN empresa_actividades vea ON vea.id = v.actividad_id
         LEFT JOIN actividad_punto_venta pvm ON pvm.empresa_id = ? AND pvm.punto_venta = v.punto_venta
         LEFT JOIN empresa_actividades pvea ON pvea.id = pvm.actividad_id';

    /**
     * Ventas gravadas (neto + IVA) por actividad, condición de IVA, bien de uso y alícuota.
     *
     * @return list<array{actividad_codigo: ?string, condicion_iva_id: ?int, es_bien_uso: string,
     *                     alicuota: ?string, neto: string, iva: string}>
     */
    public function ventasGravado(int $empresaId, int $periodoId, int $signo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::ACTIVIDAD_RESUELTA . ' AS actividad_codigo,
                    v.condicion_iva_id AS condicion_iva_id, v.es_bien_uso AS es_bien_uso,
                    vd.iva_alicuota AS alicuota,
                    SUM(vd.neto_gravado) AS neto, SUM(vd.iva_importe) AS iva
               FROM venta_discriminaciones vd
               JOIN ventas v ON vd.venta_id = v.id
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
               ' . self::JOIN_ACTIVIDAD . '
              WHERE v.periodo_id = ? AND COALESCE(tc.signo, 1) ' . ($signo < 0 ? '<' : '>') . ' 0
                    AND vd.neto_gravado <> 0
              GROUP BY actividad_codigo, v.condicion_iva_id, v.es_bien_uso, vd.iva_alicuota'
        );
        $stmt->execute([$empresaId, $periodoId]);

        $out = [];
        foreach ((array) $stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'actividad_codigo' => $r['actividad_codigo'] !== null ? (string) $r['actividad_codigo'] : null,
                'condicion_iva_id' => $r['condicion_iva_id'] !== null ? (int) $r['condicion_iva_id'] : null,
                'es_bien_uso'      => (string) ($r['es_bien_uso'] ?? 'N'),
                'alicuota'         => (string) ($r['alicuota'] ?? '0'),
                'neto'             => (string) ($r['neto'] ?? '0'),
                'iva'              => (string) ($r['iva'] ?? '0'),
            ];
        }

        return $out;
    }

    /**
     * Total exento + no gravado de ventas (a nivel comprobante) por actividad.
     *
     * @return list<array{actividad_codigo: ?string, monto: string}>
     */
    public function ventasNoGravado(int $empresaId, int $periodoId, int $signo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::ACTIVIDAD_RESUELTA . ' AS actividad_codigo,
                    SUM(v.exento + v.neto_no_grav) AS monto
               FROM ventas v
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
               ' . self::JOIN_ACTIVIDAD . '
              WHERE v.periodo_id = ? AND COALESCE(tc.signo, 1) ' . ($signo < 0 ? '<' : '>') . ' 0
                    AND (v.exento + v.neto_no_grav) <> 0
              GROUP BY actividad_codigo'
        );
        $stmt->execute([$empresaId, $periodoId]);

        $out = [];
        foreach ((array) $stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'actividad_codigo' => $r['actividad_codigo'] !== null ? (string) $r['actividad_codigo'] : null,
                'monto'            => (string) ($r['monto'] ?? '0'),
            ];
        }

        return $out;
    }

    /**
     * Compras gravadas (neto + IVA facturado + crédito fiscal computable) por concepto y alícuota.
     *
     * @return list<array{concepto: ?int, alicuota: ?string, neto: string, iva: string, cf: string}>
     */
    public function comprasGravado(int $periodoId, int $signo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.concepto_dj AS concepto, cd.iva_alicuota AS alicuota, SUM(cd.neto_gravado) AS neto,
                    SUM(cd.iva_importe) AS iva, SUM(cd.cf_computable) AS cf
               FROM compra_discriminaciones cd
               JOIN compras c ON cd.compra_id = c.id
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
              WHERE c.periodo_id = ? AND COALESCE(tc.signo, 1) ' . ($signo < 0 ? '<' : '>') . ' 0
                    AND cd.neto_gravado <> 0
              GROUP BY c.concepto_dj, cd.iva_alicuota'
        );
        $stmt->execute([$periodoId]);

        $out = [];
        foreach ((array) $stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = [
                'concepto' => $r['concepto'] !== null ? (int) $r['concepto'] : null,
                'alicuota' => (string) ($r['alicuota'] ?? '0'),
                'neto'     => (string) ($r['neto'] ?? '0'),
                'iva'      => (string) ($r['iva'] ?? '0'),
                'cf'       => (string) ($r['cf'] ?? '0'),
            ];
        }

        return $out;
    }
}
