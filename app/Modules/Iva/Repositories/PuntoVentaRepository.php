<?php

namespace App\Modules\Iva\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/** Persistencia de puntos de venta por empresa (para numeración de factura electrónica). */
class PuntoVentaRepository
{
    private const WRITABLE = ['numero', 'descripcion', 'tipo_emision', 'activo'];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAllByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM puntos_venta WHERE empresa_id = ? ORDER BY numero');
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM puntos_venta WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Punto de venta', $id);
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public function findByNumero(int $numero, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM puntos_venta WHERE numero = ? AND empresa_id = ?');
        $stmt->execute([$numero, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
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

        $this->pdo->prepare(sprintf(
            'INSERT INTO puntos_venta (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        ))->execute($fields);

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

        $stmt = $this->pdo->prepare("UPDATE puntos_venta SET {$set} WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM puntos_venta WHERE id = ? AND empresa_id = ?');
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
