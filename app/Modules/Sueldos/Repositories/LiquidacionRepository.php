<?php

namespace App\Modules\Sueldos\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia de liquidaciones, novedades y recibos. Acotado a `empresa_id` (la
 * pertenencia al tenant la valida el Service). Los multi-insert (novedades/recibos)
 * corren dentro de la transacción que abre el Service.
 */
class LiquidacionRepository
{
    private const WRITABLE = [
        'periodo_liquidado', 'descripcion', 'tipo', 'fecha_desde', 'fecha_hasta',
        'fecha_pago', 'activa', 'bloqueada',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAllByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM liquidaciones WHERE empresa_id = ? ORDER BY periodo_liquidado DESC, id DESC'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM liquidaciones WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Liquidacion', $id);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId): array
    {
        $fields = array_intersect_key($data, array_flip(self::WRITABLE));
        $fields['empresa_id'] = $empresaId;

        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO liquidaciones (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        );
        $this->pdo->prepare($sql)->execute($fields);

        return $this->findById((int) $this->pdo->lastInsertId(), $empresaId);
    }

    public function delete(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM liquidaciones WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);

        return $stmt->rowCount() > 0;
    }

    // ── Conceptos / categoría (para liquidar) ───────────────────────────────────

    /** @return list<array<string, mixed>> conceptos ordenados de la empresa */
    public function conceptosDeEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, codigo, descripcion, formula, tipo FROM conceptos
              WHERE empresa_id = ? ORDER BY orden, codigo'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function valorCategoria(int $categoriaId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT valor FROM sueldos_categorias WHERE id = ?');
        $stmt->execute([$categoriaId]);
        $valor = $stmt->fetchColumn();

        return $valor === false ? null : (string) $valor;
    }

    // ── Novedades ───────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    public function novedades(int $liquidacionId, int $empleadoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM novedades WHERE liquidacion_id = ? AND empleado_id = ? ORDER BY concepto_id'
        );
        $stmt->execute([$liquidacionId, $empleadoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reemplaza las novedades del empleado en la liquidación. Dentro de transacción.
     *
     * @param list<array{concepto_id: int, cantidad?: mixed, importe?: mixed}> $items
     */
    public function replaceNovedades(int $liquidacionId, int $empleadoId, array $items): void
    {
        $del = $this->pdo->prepare('DELETE FROM novedades WHERE liquidacion_id = ? AND empleado_id = ?');
        $del->execute([$liquidacionId, $empleadoId]);

        $ins = $this->pdo->prepare(
            'INSERT INTO novedades (liquidacion_id, empleado_id, concepto_id, cantidad, importe)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $ins->execute([
                $liquidacionId,
                $empleadoId,
                $item['concepto_id'],
                $item['cantidad'] ?? 0,
                $item['importe'] ?? 0,
            ]);
        }
    }

    // ── Snapshot del legajo al liquidar ─────────────────────────────────────────

    /** @return array<string, mixed>|null */
    public function snapshot(int $liquidacionId, int $empleadoId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM liquidacion_empleados WHERE liquidacion_id = ? AND empleado_id = ?'
        );
        $stmt->execute([$liquidacionId, $empleadoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Congela los datos del empleado para la liquidación. Dentro de transacción.
     *
     * @param array<string, mixed> $datos
     */
    public function upsertSnapshot(int $liquidacionId, int $empleadoId, array $datos): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO liquidacion_empleados
                (liquidacion_id, empleado_id, legajo, nombres, primer_apellido, segundo_apellido,
                 cuil, fecha_ingreso, basico, antiguedad)
             VALUES (:liquidacion_id, :empleado_id, :legajo, :nombres, :primer_apellido, :segundo_apellido,
                 :cuil, :fecha_ingreso, :basico, :antiguedad)
             ON DUPLICATE KEY UPDATE
                legajo = VALUES(legajo), nombres = VALUES(nombres),
                primer_apellido = VALUES(primer_apellido), segundo_apellido = VALUES(segundo_apellido),
                cuil = VALUES(cuil), fecha_ingreso = VALUES(fecha_ingreso),
                basico = VALUES(basico), antiguedad = VALUES(antiguedad)'
        );
        $stmt->execute([
            'liquidacion_id'   => $liquidacionId,
            'empleado_id'      => $empleadoId,
            'legajo'           => $datos['legajo'] ?? null,
            'nombres'          => $datos['nombres'] ?? null,
            'primer_apellido'  => $datos['primer_apellido'] ?? null,
            'segundo_apellido' => $datos['segundo_apellido'] ?? null,
            'cuil'             => $datos['cuil'] ?? null,
            'fecha_ingreso'    => $datos['fecha_ingreso'] ?? null,
            'basico'           => $datos['basico'] ?? 0,
            'antiguedad'       => $datos['antiguedad'] ?? 0,
        ]);
    }

    // ── Recibos ─────────────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    public function recibos(int $liquidacionId, int $empleadoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM recibos WHERE liquidacion_id = ? AND empleado_id = ? ORDER BY item'
        );
        $stmt->execute([$liquidacionId, $empleadoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reemplaza los recibos del empleado en la liquidación. Dentro de transacción.
     *
     * @param list<array<string, mixed>> $lineas
     */
    public function replaceRecibos(int $liquidacionId, int $empleadoId, array $lineas): void
    {
        $del = $this->pdo->prepare('DELETE FROM recibos WHERE liquidacion_id = ? AND empleado_id = ?');
        $del->execute([$liquidacionId, $empleadoId]);

        $ins = $this->pdo->prepare(
            'INSERT INTO recibos
                (liquidacion_id, empleado_id, item, concepto_id, codigo, descripcion, formula, cantidad, importe, tipo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lineas as $l) {
            $ins->execute([
                $liquidacionId,
                $empleadoId,
                $l['item'],
                $l['concepto_id'] ?? null,
                $l['codigo'] ?? null,
                $l['descripcion'] ?? null,
                $l['formula'] ?? null,
                $l['cantidad'] ?? 0,
                $l['importe'] ?? 0,
                $l['tipo'] ?? 1,
            ]);
        }
    }
}
