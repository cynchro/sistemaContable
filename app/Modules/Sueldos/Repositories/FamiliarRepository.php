<?php

namespace App\Modules\Sueldos\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia del grupo familiar de un empleado. Acotado a `empleado_id`; la
 * pertenencia del empleado a la empresa/tenant la valida el Service.
 */
class FamiliarRepository
{
    private const WRITABLE = [
        'parentesco_id', 'nombre', 'tipo_documento_id', 'numero_documento',
        'fecha_nacimiento', 'escolarizado', 'discapacitado', 'deduce_ganancias', 'porc_deduccion',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAllByEmpleado(int $empleadoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM familiares WHERE empleado_id = ? ORDER BY nombre');
        $stmt->execute([$empleadoId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empleadoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM familiares WHERE id = ? AND empleado_id = ?');
        $stmt->execute([$id, $empleadoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Familiar', $id);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empleadoId): array
    {
        $fields = $this->writableFrom($data);
        $fields['empleado_id'] = $empleadoId;

        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO familiares (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        );
        $this->pdo->prepare($sql)->execute($fields);

        return $this->findById((int) $this->pdo->lastInsertId(), $empleadoId);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, int $empleadoId): bool
    {
        $fields = $this->writableFrom($data);

        if ($fields === []) {
            return false;
        }

        $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
        $fields['id']          = $id;
        $fields['empleado_id'] = $empleadoId;

        $stmt = $this->pdo->prepare("UPDATE familiares SET {$set} WHERE id = :id AND empleado_id = :empleado_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $empleadoId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM familiares WHERE id = ? AND empleado_id = ?');
        $stmt->execute([$id, $empleadoId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function writableFrom(array $data): array
    {
        return array_intersect_key($data, array_flip(self::WRITABLE));
    }
}
