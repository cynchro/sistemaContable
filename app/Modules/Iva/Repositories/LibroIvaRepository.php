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
}
