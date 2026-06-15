<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Sumas agrupadas por signo (de tipo_comprobante) para los totales de período.
 * La agregación se hace en SQL con DECIMAL (exacta) y devuelve pocas filas (una
 * por signo); la LibroIvaCalculator las combina con signo. Acotado a `periodo_id`
 * (la pertenencia del período la valida el Service).
 */
class LibroIvaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array{signo: int, importe: string}> */
    public function ventasTotalPorSigno(int $periodoId): array
    {
        return $this->run(
            'SELECT COALESCE(tc.signo, 1) AS signo, SUM(v.total) AS importe
               FROM ventas v
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
              WHERE v.periodo_id = ?
              GROUP BY COALESCE(tc.signo, 1)',
            $periodoId,
        );
    }

    /** @return list<array{signo: int, importe: string}> */
    public function ventasIvaPorSigno(int $periodoId): array
    {
        return $this->run(
            'SELECT COALESCE(tc.signo, 1) AS signo, SUM(vd.iva_importe) AS importe
               FROM venta_discriminaciones vd
               JOIN ventas v ON vd.venta_id = v.id
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
              WHERE v.periodo_id = ?
              GROUP BY COALESCE(tc.signo, 1)',
            $periodoId,
        );
    }

    /** @return list<array{signo: int, importe: string}> */
    public function comprasTotalPorSigno(int $periodoId): array
    {
        return $this->run(
            'SELECT COALESCE(tc.signo, 1) AS signo, SUM(c.total) AS importe
               FROM compras c
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
              WHERE c.periodo_id = ?
              GROUP BY COALESCE(tc.signo, 1)',
            $periodoId,
        );
    }

    /** @return list<array{signo: int, importe: string}> */
    public function comprasIvaPorSigno(int $periodoId): array
    {
        return $this->run(
            'SELECT COALESCE(tc.signo, 1) AS signo, SUM(cd.iva_importe) AS importe
               FROM compra_discriminaciones cd
               JOIN compras c ON cd.compra_id = c.id
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
              WHERE c.periodo_id = ?
              GROUP BY COALESCE(tc.signo, 1)',
            $periodoId,
        );
    }

    /**
     * Detalle de ventas por condición IVA, alícuota y signo (para libro/DDJJ).
     * Cada fila: condicion_iva_id, alicuota, signo, neto_gravado, iva.
     *
     * @return list<array<string, mixed>>
     */
    public function ventasDetallePorAlicuota(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.condicion_iva_id, vd.iva_alicuota AS alicuota, COALESCE(tc.signo, 1) AS signo,
                    SUM(vd.neto_gravado) AS neto_gravado, SUM(vd.iva_importe) AS iva
               FROM venta_discriminaciones vd
               JOIN ventas v ON vd.venta_id = v.id
               LEFT JOIN tipos_comprobante tc ON v.tipo_comprobante_id = tc.id
              WHERE v.periodo_id = ?
              GROUP BY v.condicion_iva_id, vd.iva_alicuota, COALESCE(tc.signo, 1)'
        );
        $stmt->execute([$periodoId]);

        return $this->mapDetalle((array) $stmt->fetchAll(PDO::FETCH_ASSOC), false);
    }

    /**
     * Detalle de compras por condición IVA, alícuota y signo (incluye cf_computable).
     * Cada fila: condicion_iva_id, alicuota, signo, neto_gravado, iva, cf_computable.
     *
     * @return list<array<string, mixed>>
     */
    public function comprasDetallePorAlicuota(int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.condicion_iva_id, cd.iva_alicuota AS alicuota, COALESCE(tc.signo, 1) AS signo,
                    SUM(cd.neto_gravado) AS neto_gravado, SUM(cd.iva_importe) AS iva,
                    SUM(cd.cf_computable) AS cf_computable
               FROM compra_discriminaciones cd
               JOIN compras c ON cd.compra_id = c.id
               LEFT JOIN tipos_comprobante tc ON c.tipo_comprobante_id = tc.id
              WHERE c.periodo_id = ?
              GROUP BY c.condicion_iva_id, cd.iva_alicuota, COALESCE(tc.signo, 1)'
        );
        $stmt->execute([$periodoId]);

        return $this->mapDetalle((array) $stmt->fetchAll(PDO::FETCH_ASSOC), true);
    }

    /**
     * Conceptos firmados de ventas del período (no gravado, exento, imp. interno, total).
     *
     * @return array{no_gravado: string, exento: string, imp_interno: string, total: string}
     */
    public function ventasConceptos(int $periodoId): array
    {
        return $this->conceptos('ventas', $periodoId);
    }

    /**
     * Conceptos firmados de compras del período.
     *
     * @return array{no_gravado: string, exento: string, imp_interno: string, total: string}
     */
    public function comprasConceptos(int $periodoId): array
    {
        return $this->conceptos('compras', $periodoId);
    }

    /**
     * Sumas firmadas de los conceptos de cabecera para una tabla de comprobantes.
     *
     * @param  'ventas'|'compras' $tabla
     * @return array{no_gravado: string, exento: string, imp_interno: string, total: string}
     */
    private function conceptos(string $tabla, int $periodoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(t.neto_no_grav * COALESCE(tc.signo, 1)), 0) AS no_gravado,
                COALESCE(SUM(t.exento      * COALESCE(tc.signo, 1)), 0) AS exento,
                COALESCE(SUM(t.imp_interno * COALESCE(tc.signo, 1)), 0) AS imp_interno,
                COALESCE(SUM(t.total       * COALESCE(tc.signo, 1)), 0) AS total
               FROM {$tabla} t
               LEFT JOIN tipos_comprobante tc ON t.tipo_comprobante_id = tc.id
              WHERE t.periodo_id = ?"
        );
        $stmt->execute([$periodoId]);
        /** @var array<string, mixed> $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'no_gravado'  => (string) ($row['no_gravado'] ?? '0'),
            'exento'      => (string) ($row['exento'] ?? '0'),
            'imp_interno' => (string) ($row['imp_interno'] ?? '0'),
            'total'       => (string) ($row['total'] ?? '0'),
        ];
    }

    /** @return list<array{signo: int, importe: string}> */
    private function run(string $sql, int $periodoId): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$periodoId]);

        $rows = [];
        foreach ((array) $stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'signo'   => (int) $row['signo'],
                'importe' => (string) ($row['importe'] ?? '0'),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>> $raw
     * @return list<array<string, mixed>>
     */
    private function mapDetalle(array $raw, bool $conCf): array
    {
        $rows = [];
        foreach ($raw as $row) {
            $item = [
                'condicion_iva_id' => $row['condicion_iva_id'] !== null ? (int) $row['condicion_iva_id'] : null,
                'alicuota'         => $row['alicuota'] !== null ? (string) $row['alicuota'] : null,
                'signo'            => (int) $row['signo'],
                'neto_gravado'     => (string) ($row['neto_gravado'] ?? '0'),
                'iva'              => (string) ($row['iva'] ?? '0'),
            ];
            if ($conCf) {
                $item['cf_computable'] = (string) ($row['cf_computable'] ?? '0');
            }
            $rows[] = $item;
        }

        return $rows;
    }
}
