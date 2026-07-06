<?php

namespace App\Modules\Iva\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia de clientes del módulo IVA. Acotado a `empresa_id`; la pertenencia
 * de la empresa al tenant la valida el Service vía EmpresaRepository.
 */
class IvaClienteRepository
{
    private const WRITABLE = [
        'condicion_iva_id', 'provincia_id', 'cuenta_id', 'rubro_id',
        'nombre', 'cuit', 'domicilio', 'localidad', 'telefono', 'ingresos_brutos', 'esglobal',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Clientes de la empresa + los marcados `esglobal` de cualquier empresa del mismo
     * estudio (tenant): así un cliente cargado una vez se ve en todas las empresas.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllByEmpresa(int $empresaId, string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.* FROM iva_clientes c
               JOIN empresas e ON c.empresa_id = e.id
              WHERE c.empresa_id = ?
                 OR (c.esglobal = ? AND e.tenant_id = ?)
              ORDER BY c.nombre'
        );
        $stmt->execute([$empresaId, 'S', $tenantId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM iva_clientes WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Cliente', $id);
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
            'INSERT INTO iva_clientes (%s) VALUES (%s)',
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

        $stmt = $this->pdo->prepare("UPDATE iva_clientes SET {$set} WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM iva_clientes WHERE id = ? AND empresa_id = ?');
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
