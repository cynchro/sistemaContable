<?php

namespace App\Modules\Iva\Repositories;

use PDO;

/**
 * Persistencia de la DDJJ "IVA Simple" (F.2051) por período. Una fila por
 * (empresa, período): `guardar` hace upsert. `findAnterior` devuelve la DDJJ del
 * período inmediato anterior (por fecha de inicio), de donde salen los arrastres.
 * La pertenencia de empresa/período al tenant la valida el Service.
 */
class DdjjSimpleRepository
{
    /** Campos persistidos del snapshot del cálculo. */
    private const CAMPOS = [
        'debito_fiscal',
        'credito_fiscal',
        'saldo_tecnico_anterior',
        'saldo_tecnico_a_favor_arca',
        'saldo_tecnico_a_favor_contribuyente',
        'saldo_libre_disponibilidad_anterior',
        'retenciones_percepciones_pagos',
        'saldo_a_pagar',
        'saldo_libre_disponibilidad_periodo',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByPeriodo(int $empresaId, int $periodoId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM iva_ddjj_simple WHERE empresa_id = ? AND periodo_id = ?'
        );
        $stmt->execute([$empresaId, $periodoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * DDJJ del período inmediato anterior de la empresa (menor `fecha_ini` que el
     * período dado). De acá salen el saldo técnico y el saldo de libre disponibilidad
     * que arrastran al período actual. Null si no hay anterior con DDJJ presentada.
     *
     * @return array<string, mixed>|null
     */
    public function findAnterior(int $empresaId, int $periodoId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*
               FROM iva_ddjj_simple d
               JOIN periodos p ON d.periodo_id = p.id
              WHERE d.empresa_id = ?
                AND p.fecha_ini IS NOT NULL
                AND p.fecha_ini < (SELECT fecha_ini FROM periodos WHERE id = ?)
              ORDER BY p.fecha_ini DESC, p.id DESC
              LIMIT 1'
        );
        $stmt->execute([$empresaId, $periodoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Upsert de la DDJJ del período (única por empresa+período).
     *
     * @param array<string, string> $datos Valores de los campos de self::CAMPOS.
     */
    public function guardar(int $empresaId, int $periodoId, array $datos): void
    {
        $cols   = array_merge(['empresa_id', 'periodo_id'], self::CAMPOS);
        $params = array_merge([$empresaId, $periodoId], array_map(
            static fn (string $c): string => (string) ($datos[$c] ?? '0'),
            self::CAMPOS,
        ));

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $updates      = implode(', ', array_map(static fn (string $c) => "{$c} = VALUES({$c})", self::CAMPOS));

        $stmt = $this->pdo->prepare(
            'INSERT INTO iva_ddjj_simple (' . implode(', ', $cols) . ")
             VALUES ({$placeholders})
             ON DUPLICATE KEY UPDATE {$updates}"
        );
        $stmt->execute($params);
    }
}
