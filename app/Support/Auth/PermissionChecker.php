<?php

namespace App\Support\Auth;

use PDO;

/**
 * Fuente única de verdad para resolver si un rol tiene un permiso y con qué
 * nivel de acceso. Centraliza el SQL antes duplicado en los middlewares de
 * autorización y en AuthRepository.
 *
 * Niveles (roles_permisos.estado):
 *   0 = sin permiso, 1 = lectura, 2 = lectura-escritura.
 *
 * Soporta jerarquía: un rol hereda los permisos de su cadena de roles padre
 * (roles.parent_id). El nivel efectivo es el máximo entre el propio rol y sus
 * ancestros.
 */
class PermissionChecker
{
    public const LEVEL_NONE  = 0;
    public const LEVEL_READ  = 1;
    public const LEVEL_WRITE = 2;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Nivel de acceso efectivo del rol sobre el permiso (considerando herencia
     * de roles padre), o LEVEL_NONE si ni el rol ni sus ancestros lo tienen.
     */
    public function level(int $rolId, string $key): int
    {
        $stmt = $this->pdo->prepare(
            'WITH RECURSIVE ancestors AS (
                SELECT id, parent_id FROM roles WHERE id = ?
                UNION ALL
                SELECT r.id, r.parent_id FROM roles r
                JOIN ancestors a ON r.id = a.parent_id
            )
            SELECT MAX(rp.estado) AS estado
            FROM ancestors a
            JOIN roles_permisos rp ON rp.rol = a.id
            JOIN permisos p ON rp.permiso = p.id
            WHERE p.`key` = ?'
        );
        $stmt->execute([$rolId, $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // MAX() sobre cero filas devuelve NULL → sin permiso.
        return $row === false
            ? self::LEVEL_NONE
            : (int) ($row['estado'] ?? self::LEVEL_NONE);
    }

    /** ¿El rol tiene el permiso con al menos el nivel requerido? */
    public function allows(int $rolId, string $key, int $minLevel = self::LEVEL_READ): bool
    {
        return $this->level($rolId, $key) >= $minLevel;
    }
}
