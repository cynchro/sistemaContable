<?php

namespace App\Modules\Iva\Repositories;

use PDO;
use App\Helpers\PaginatorHelper;
use App\Exceptions\NotFoundException;

/**
 * Cola de pedidos de "Liquidar IVA" (plan 25/08/2026): un usuario crea el pedido, el worker
 * externo del bot lo toma y reporta el resultado. `tomarSiguientePendiente()` es la única
 * escritura que hace el worker sin conocer el `id` de antemano — atómica a propósito, aunque
 * hoy solo haya un worker, para no depender de esa suposición.
 */
class LiquidacionRepository
{
    private const ESTADOS_ABIERTOS = ['pendiente', 'tomada', 'en_curso'];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed> paginado (ver PaginatorHelper) */
    public function findPaginado(int $empresaId, int $periodoId, int $page, int $perPage): array
    {
        $query = 'SELECT * FROM iva_liquidaciones WHERE empresa_id = ? AND periodo_id = ? ORDER BY created_at DESC';

        return (new PaginatorHelper($this->pdo, $query, $page, $perPage, true, [$empresaId, $periodoId]))
            ->getPaginatedResults();
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM iva_liquidaciones WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Liquidación', $id);
        }

        return $row;
    }

    /**
     * Para los endpoints del worker: no hay `{empresaId}` en la URL (`/iva/liquidaciones/{id}/...`),
     * así que se acota por tenant vía JOIN — la API key del bot pertenece a un único tenant
     * (un estudio), nunca debe poder tocar la liquidación de otro.
     *
     * @return array<string, mixed>
     */
    public function findByIdParaTenant(int $id, string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.* FROM iva_liquidaciones l
              JOIN empresas e ON e.id = l.empresa_id
             WHERE l.id = ? AND e.tenant_id = ?'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Liquidación', $id);
        }

        return $row;
    }

    /** Ya hay un pedido en curso (sin terminar) para esa empresa+período. */
    public function existeAbierta(int $empresaId, int $periodoId): bool
    {
        $in   = implode(',', array_fill(0, count(self::ESTADOS_ABIERTOS), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM iva_liquidaciones WHERE empresa_id = ? AND periodo_id = ? AND estado IN ({$in}) LIMIT 1"
        );
        $stmt->execute([$empresaId, $periodoId, ...self::ESTADOS_ABIERTOS]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param  array{direccion: string, libro: string, periodo_arca: string} $data
     * @return array<string, mixed>
     */
    public function create(array $data, int $empresaId, int $periodoId, int $creadoPor): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO iva_liquidaciones
                (empresa_id, periodo_id, direccion, libro, periodo_arca, estado, creado_por)
             VALUES (?, ?, ?, ?, ?, \'pendiente\', ?)'
        );
        $stmt->execute([
            $empresaId, $periodoId, $data['direccion'], $data['libro'], $data['periodo_arca'], $creadoPor,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId(), $empresaId);
    }

    /**
     * Toma atómicamente la liquidación pendiente más antigua del tenant (si hay) y la marca
     * `tomada`. `UPDATE ... WHERE estado='pendiente'` es atómico en MySQL/InnoDB (bloquea la
     * fila elegida antes de que otro worker concurrente pueda tomarla) — no hace falta un
     * `SELECT ... FOR UPDATE` explícito en una transacción aparte. Acotada al tenant de la API
     * key que llama (un worker/bot pertenece a un único estudio).
     *
     * @return array<string, mixed>|null
     */
    public function tomarSiguientePendiente(string $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.id FROM iva_liquidaciones l
              JOIN empresas e ON e.id = l.empresa_id
             WHERE l.estado = 'pendiente' AND e.tenant_id = ?
             ORDER BY l.created_at LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $id = (int) ($stmt->fetchColumn() ?: 0);

        if ($id === 0) {
            return null;
        }

        $update = $this->pdo->prepare(
            "UPDATE iva_liquidaciones SET estado = 'tomada', tomada_en = NOW()
              WHERE id = ? AND estado = 'pendiente'"
        );
        $update->execute([$id]);

        // rowCount() === 0 → otro worker la tomó primero entre el SELECT y el UPDATE de arriba.
        return $update->rowCount() > 0 ? $this->findByIdParaTenant($id, $tenantId) : null;
    }

    public function actualizarEstado(int $id, string $estado, ?string $resultado): void
    {
        $terminal = in_array($estado, ['terminada', 'error'], true);
        $sql      = 'UPDATE iva_liquidaciones SET estado = ?, resultado = ?'
            . ($terminal ? ', terminada_en = NOW()' : '')
            . ' WHERE id = ?';

        $this->pdo->prepare($sql)->execute([$estado, $resultado, $id]);
    }
}
