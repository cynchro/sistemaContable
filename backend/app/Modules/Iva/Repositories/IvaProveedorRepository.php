<?php

namespace App\Modules\Iva\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia de proveedores del módulo IVA. Acotado a `empresa_id`; la
 * pertenencia de la empresa al tenant la valida el Service vía EmpresaRepository.
 */
class IvaProveedorRepository
{
    private const WRITABLE = [
        'condicion_iva_id', 'provincia_id', 'cuenta_id', 'rubro_id',
        'nombre', 'cuit', 'domicilio', 'localidad', 'telefono', 'cp',
        'ingresos_brutos', 'cai', 'fecha_cai', 'cais', 'esglobal',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAllByEmpresa(int $empresaId, string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.* FROM iva_proveedores p
               JOIN empresas e ON p.empresa_id = e.id
              WHERE p.empresa_id = ?
                 OR (p.esglobal = ? AND e.tenant_id = ?)
              ORDER BY p.nombre'
        );
        $stmt->execute([$empresaId, 'S', $tenantId]);

        return array_map([$this, 'decode'], (array) $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM iva_proveedores WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Proveedor', $id);
        }

        return $this->decode($row);
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
            'INSERT INTO iva_proveedores (%s) VALUES (%s)',
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

        $stmt = $this->pdo->prepare("UPDATE iva_proveedores SET {$set} WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM iva_proveedores WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function writableFrom(array $data): array
    {
        $fields = array_intersect_key($data, array_flip(self::WRITABLE));
        // La lista de CAI se persiste como JSON (hasta 5 {numero, vencimiento}).
        if (array_key_exists('cais', $fields)) {
            $fields['cais'] = is_array($fields['cais'])
                ? (json_encode(array_values($fields['cais'])) ?: null)
                : null;
        }

        return $fields;
    }

    /**
     * Decodifica el JSON de `cais` a un arreglo para la respuesta.
     *
     * @param  array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        $row['cais'] = is_string($row['cais'] ?? null)
            ? (json_decode((string) $row['cais'], true) ?: [])
            : [];

        return $row;
    }
}
