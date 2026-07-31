<?php

namespace App\Modules\Iva\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Padrón Único de Sujetos IVA: una fila por CUIT, compartida por todas las empresas
 * del tenant (estudio). Reemplaza `iva_clientes`/`iva_proveedores` — la activación por
 * empresa (rol cliente/proveedor) vive en `SujetoEmpresaRepository`.
 */
class SujetoRepository
{
    private const WRITABLE = [
        'cuit', 'nombre', 'domicilio', 'localidad', 'cp', 'condicion_iva_id', 'provincia_id',
        'ingresos_brutos', 'telefono', 'cai', 'fecha_cai', 'cais', 'concepto_default_id',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Todos los sujetos del padrón del tenant, sin filtrar por empresa (vista global —
     * documento "Satélite Visual IVA" §10, Etapa 4: "consulta del padrón global").
     *
     * @param  array{q?: ?string} $filtros
     * @return list<array<string, mixed>>
     */
    public function listAllByTenant(string $tenantId, array $filtros = []): array
    {
        $where  = ['tenant_id = ?'];
        $params = [$tenantId];

        if (!empty($filtros['q'])) {
            $where[]  = '(nombre LIKE ? OR cuit LIKE ?)';
            $q        = '%' . $filtros['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM iva_sujetos WHERE ' . implode(' AND ', $where) . ' ORDER BY nombre'
        );
        $stmt->execute($params);

        return array_map([$this, 'decode'], (array) $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed>|null */
    public function findByCuit(string $tenantId, string $cuit): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM iva_sujetos WHERE tenant_id = ? AND cuit = ?');
        $stmt->execute([$tenantId, $cuit]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->decode($row) : null;
    }

    /** @return array<string, mixed> */
    public function findById(int $id, string $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM iva_sujetos WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Sujeto', $id);
        }

        return $this->decode($row);
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
            'INSERT INTO iva_sujetos (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        );
        $this->pdo->prepare($sql)->execute($fields);

        return $this->findById((int) $this->pdo->lastInsertId(), $tenantId);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data, string $tenantId): bool
    {
        $fields = $this->writableFrom($data);

        if ($fields === []) {
            return false;
        }

        $set = implode(', ', array_map(static fn (string $c) => "{$c} = :{$c}", array_keys($fields)));
        $fields['id']        = $id;
        $fields['tenant_id'] = $tenantId;

        $stmt = $this->pdo->prepare("UPDATE iva_sujetos SET {$set} WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute($fields);

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
