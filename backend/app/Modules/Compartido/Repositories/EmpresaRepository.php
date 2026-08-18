<?php

namespace App\Modules\Compartido\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia de empresas. Toda operación se filtra por `tenant_id` (estudio):
 * aislamiento row-level — un estudio nunca ve ni toca empresas de otro.
 */
class EmpresaRepository
{
    /** Columnas escribibles desde la API (tenant_id e id se gestionan aparte). */
    private const WRITABLE = [
        'condicion_iva_id', 'provincia_id', 'nombre', 'cuit', 'domicilio', 'localidad', 'telefono',
        'ingresos_brutos', 'establecimiento', 'nro_libro_iva', 'nro_libro_iva_ven',
        'fecha_inicio_actividades', 'actividad1_id', 'actividad2_id', 'exporta', 'inactiva',
        // CRM del contribuyente (de sistemaCuarto)
        'email', 'contacto', 'tipo_persona', 'inscripcion', 'contabilidad',
        // Trazabilidad de origen cuando el alta se autocompletó desde el SIGE
        'sige_persona_id', 'sige_synced_at',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAll(string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM empresas WHERE tenant_id = ? ORDER BY nombre');
        $stmt->execute([$tenantId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Igual que {@see self::findAll()} pero acotado a una lista de ids — usado para filtrar por
     * "empresas asignadas" (`UsuarioEmpresaRepository`, WhatsApp con el cliente 11/08/2026).
     *
     * @param  list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function findAllPorIds(string $tenantId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt         = $this->pdo->prepare(
            "SELECT * FROM empresas WHERE tenant_id = ? AND id IN ({$placeholders}) ORDER BY nombre"
        );
        $stmt->execute([$tenantId, ...$ids]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM empresas WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Empresa', $id);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, string $tenantId): array
    {
        $fields = $this->writableFrom($data);
        $fields['tenant_id'] = $tenantId;

        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO empresas (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $this->pdo->prepare($sql)->execute($fields);

        return $this->findById((int) $this->pdo->lastInsertId(), $tenantId);
    }

    /**
     * @param array<string, mixed> $data
     * @return bool  true si modificó alguna fila.
     */
    public function update(int $id, array $data, string $tenantId): bool
    {
        $fields = $this->writableFrom($data);

        if ($fields === []) {
            return false;
        }

        $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
        $fields['id']        = $id;
        $fields['tenant_id'] = $tenantId;

        $stmt = $this->pdo->prepare("UPDATE empresas SET {$set} WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, string $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM empresas WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Filtra el input a las columnas escribibles permitidas (lista blanca).
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function writableFrom(array $data): array
    {
        return array_intersect_key($data, array_flip(self::WRITABLE));
    }
}
