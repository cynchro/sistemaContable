<?php

namespace App\Modules\Compartido\Repositories;

use PDO;
use App\Support\DB;

/**
 * Empresas "asignadas" a un usuario (WhatsApp con el cliente, 11/08/2026: "me gustaría que de
 * última él pueda ver sus empresas asignadas"). Asignación opcional, no restrictiva: si un
 * usuario no tiene ninguna fila acá, sigue viendo todas las empresas del tenant — esto es un
 * filtro de visibilidad/comodidad, no la restricción dura de permisos por contribuyente que el
 * cliente pidió dejar pendiente.
 */
class UsuarioEmpresaRepository
{
    public function __construct(private PDO $pdo, private DB $db)
    {
    }

    /** @return list<int> */
    public function idsDe(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT empresa_id FROM usuario_empresas WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);

        return array_map('intval', (array) $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<array{id: int, nombre: string, cuit: ?string}> */
    public function empresasDe(int $usuarioId, string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.nombre, e.cuit
               FROM usuario_empresas ue
               JOIN empresas e ON e.id = ue.empresa_id
              WHERE ue.usuario_id = ? AND e.tenant_id = ?
              ORDER BY e.nombre'
        );
        $stmt->execute([$usuarioId, $tenantId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reemplaza la lista completa de asignaciones del usuario (no incremental) — más simple
     * para una UI de "elegí las empresas de esta persona" con checkboxes.
     *
     * @param  list<int> $empresaIds
     */
    public function asignar(int $usuarioId, array $empresaIds): void
    {
        $this->db->withTransaction(function () use ($usuarioId, $empresaIds): void {
            $del = $this->pdo->prepare('DELETE FROM usuario_empresas WHERE usuario_id = ?');
            $del->execute([$usuarioId]);

            if ($empresaIds !== []) {
                $placeholders = implode(',', array_fill(0, count($empresaIds), '(?, ?)'));
                $ins          = $this->pdo->prepare(
                    "INSERT INTO usuario_empresas (usuario_id, empresa_id) VALUES {$placeholders}"
                );
                $params = [];
                foreach ($empresaIds as $empresaId) {
                    $params[] = $usuarioId;
                    $params[] = $empresaId;
                }
                $ins->execute($params);
            }
        });
    }
}
