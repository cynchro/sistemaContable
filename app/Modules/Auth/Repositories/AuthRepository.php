<?php

namespace App\Modules\Auth\Repositories;

use PDO;
use PDOException;
use App\Exceptions\DatabaseException;
use App\Exceptions\ValidationException;

class AuthRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $username, string $hashedPassword, int $rol): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO usuarios (usuario, clave, rol) VALUES (?, ?, ?)'
            );
            $stmt->execute([$username, $hashedPassword, $rol]);
            return ['id' => $this->pdo->lastInsertId()];
        } catch (\PDOException $e) {
            // SQLSTATE 23000: integrity constraint violation (duplicate unique key)
            if ($e->getCode() === '23000') {
                throw new ValidationException(['usuario' => ['El usuario ya existe en el sistema.']]);
            }
            throw new DatabaseException('Error al crear el usuario.');
        }
    }

    public function updateToken(int|string $userId, string $token): void
    {
        $stmt = $this->pdo->prepare('UPDATE usuarios SET token = ? WHERE id = ?');
        $stmt->execute([$token, $userId]);
    }

    public function clearToken(string $token): bool
    {
        $stmt = $this->pdo->prepare('UPDATE usuarios SET token = NULL WHERE token = ?');
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }

    public function findUserByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.usuario, u.rol, u.tenant_id, r.nombre AS nombre_rol
             FROM usuarios u
             LEFT JOIN roles r ON u.rol = r.id
             WHERE u.token = ?'
        );
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findUserByName(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, clave, rol, tenant_id FROM usuarios WHERE usuario = ?'
        );
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findUserById(int|string $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.usuario, u.rol, u.tenant_id, r.nombre
             FROM usuarios u
             LEFT JOIN roles r ON u.rol = r.id
             WHERE u.id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveRefreshToken(int $userId, string $token, string $expiresAt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $token, $expiresAt]);
    }

    public function findRefreshToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM refresh_tokens WHERE token = ? AND expires_at > NOW()'
        );
        $stmt->execute([$token]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function deleteRefreshToken(string $token): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE token = ?');
        $stmt->execute([$token]);
    }

    public function deleteAllRefreshTokens(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public function findUserPermissions(string $token, string $key): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rp.estado AS permiso
             FROM usuarios u
             LEFT JOIN roles_permisos rp ON rp.rol = u.rol
             LEFT JOIN permisos p ON rp.permiso = p.id
             WHERE u.token = ? AND p.key = ?'
        );
        $stmt->execute([$token, $key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? ['permiso' => $result['permiso']] : ['permiso' => 0];
    }
}
