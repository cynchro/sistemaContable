<?php

namespace App\Modules\Compartido\Repositories;

use PDO;

/**
 * "Ocupación" de una empresa (WhatsApp con el cliente, 11/08/2026): un usuario a la vez
 * trabaja sobre un contribuyente. Sin fila para una empresa = libre. `ultimo_ping` sostiene
 * un heartbeat del frontend — una fila cuyo último ping supera el timeout se trata como libre
 * sin necesidad de un job de limpieza aparte (se pisa sola en el próximo `ocupar`).
 */
class EmpresaLockRepository
{
    public const TIMEOUT_MINUTOS = 5;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Estado vigente del lock (null si está libre o venció). Acotado al tenant vía join con
     * `empresas` — evita que un lock de otro tenant se cuele en la respuesta.
     *
     * @return array{usuario_id: int, usuario_nombre: string, desde: string}|null
     */
    public function estadoDe(int $empresaId, string $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT el.usuario_id, u.usuario AS usuario_nombre, el.desde
               FROM empresa_locks el
               JOIN usuarios u ON u.id = el.usuario_id
               JOIN empresas e ON e.id = el.empresa_id
              WHERE el.empresa_id = ? AND e.tenant_id = ?
                AND el.ultimo_ping >= DATE_SUB(NOW(), INTERVAL ' . self::TIMEOUT_MINUTOS . ' MINUTE)'
        );
        $stmt->execute([$empresaId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** Reclama (o refresca, si ya era del mismo usuario) el lock. No valida conflicto — eso es del Service. */
    public function ocupar(int $empresaId, int $usuarioId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO empresa_locks (empresa_id, usuario_id, desde, ultimo_ping)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), desde = NOW(), ultimo_ping = NOW()'
        );
        $stmt->execute([$empresaId, $usuarioId]);
    }

    /** @return bool true si el ping tocó una fila realmente propia de este usuario. */
    public function ping(int $empresaId, int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE empresa_locks SET ultimo_ping = NOW() WHERE empresa_id = ? AND usuario_id = ?'
        );
        $stmt->execute([$empresaId, $usuarioId]);

        return $stmt->rowCount() > 0;
    }

    public function liberar(int $empresaId, int $usuarioId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM empresa_locks WHERE empresa_id = ? AND usuario_id = ?');
        $stmt->execute([$empresaId, $usuarioId]);
    }
}
