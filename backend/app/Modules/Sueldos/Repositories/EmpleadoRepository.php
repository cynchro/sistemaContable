<?php

namespace App\Modules\Sueldos\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia del legajo de empleados. Acotado a `empresa_id`; la pertenencia de
 * la empresa al tenant la valida el Service vía EmpresaRepository (Compartido).
 */
class EmpleadoRepository
{
    private const WRITABLE = [
        'legajo', 'nombres', 'primer_apellido', 'segundo_apellido', 'cuil',
        'tipo_documento_id', 'numero_documento', 'fecha_nacimiento', 'genero',
        'estado_civil_id', 'nacionalidad_id', 'direccion', 'localidad', 'provincia_id',
        'telefono', 'celular', 'email', 'fecha_ingreso', 'fecha_egreso', 'categoria_id',
        'basico', 'obra_social_id', 'regimen_jubilatorio_id', 'modalidad_contratacion_id',
        'situacion_revista_id', 'condicion_laboral_id', 'departamento_id', 'lugar_pago',
        'forma_de_pago', 'numero_cuenta', 'activo',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAllByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM empleados WHERE empresa_id = ? ORDER BY primer_apellido, nombres'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM empleados WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Empleado', $id);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId): array
    {
        $fields = $this->writableFrom($data);
        $fields['empresa_id'] = $empresaId;

        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);

        $sql = sprintf(
            'INSERT INTO empleados (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        );
        $this->pdo->prepare($sql)->execute($fields);

        return $this->findById((int) $this->pdo->lastInsertId(), $empresaId);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, int $empresaId): bool
    {
        $fields = $this->writableFrom($data);

        if ($fields === []) {
            return false;
        }

        $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
        $fields['id']         = $id;
        $fields['empresa_id'] = $empresaId;

        $stmt = $this->pdo->prepare("UPDATE empleados SET {$set} WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM empleados WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);

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
