<?php

namespace App\Modules\Compartido\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia de períodos. Acotado siempre a `empresa_id`; la pertenencia de la
 * empresa al tenant la valida el Service vía EmpresaRepository antes de llegar acá.
 * El estado `cerrado` no se escribe por el CRUD: se cambia con cerrar()/abrir().
 */
class PeriodoRepository
{
    /** Columnas escribibles desde el CRUD. */
    private const WRITABLE = ['nombre', 'fecha_ini', 'fecha_fin'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * ¿El período tiene comprobantes cargados (ventas o compras)? Se usa para impedir su
     * borrado (las FK son ON DELETE CASCADE: borrar el período arrastraría los comprobantes).
     * Consulta directa a las tablas del módulo Iva: es SQL del mismo sistema (monolito).
     */
    public function hasComprobantes(int $periodoId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT EXISTS(SELECT 1 FROM ventas WHERE periodo_id = :p1)
                 OR EXISTS(SELECT 1 FROM compras WHERE periodo_id = :p2)'
        );
        $stmt->execute(['p1' => $periodoId, 'p2' => $periodoId]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function findAllByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM periodos WHERE empresa_id = ? ORDER BY fecha_ini, id'
        );
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM periodos WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Periodo', $id);
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
            'INSERT INTO periodos (%s) VALUES (%s)',
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

        $stmt = $this->pdo->prepare("UPDATE periodos SET {$set} WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute($fields);

        return $stmt->rowCount() > 0;
    }

    public function setCerrado(int $id, int $empresaId, bool $cerrado): bool
    {
        $stmt = $this->pdo->prepare('UPDATE periodos SET cerrado = ? WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$cerrado ? 'S' : 'N', $id, $empresaId]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM periodos WHERE id = ? AND empresa_id = ?');
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
