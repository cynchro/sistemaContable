<?php

namespace App\Support\Auth;

use PDO;
use DateTimeInterface;

/**
 * Emisión, verificación y revocación de API keys de terceros.
 *
 * Formato del token entregado:  mk_<env>_<id>_<secret>
 *   - prefix  = "mk_<env>_<id>"  → público, indexado, sirve para el lookup.
 *   - secret  = hex aleatorio     → solo se guarda su sha256 (nunca el valor).
 *
 * El token en claro se muestra una sola vez; en DB viven `prefix` + `hash`.
 */
class ApiKeyManager
{
    public const TOKEN_PREFIX = 'mk_';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Emite una API key y persiste solo prefix + hash.
     *
     * @param  list<string> $scopes
     * @return array{token: string, id: string, prefix: string}
     */
    public function issue(
        string $tenantId,
        string $name,
        array $scopes = [],
        string $env = 'live',
        ?DateTimeInterface $expiresAt = null
    ): array {
        $id     = bin2hex(random_bytes(6));   // 12 hex → identificador público
        $secret = bin2hex(random_bytes(24));  // 48 hex → secreto
        $prefix = self::TOKEN_PREFIX . $env . '_' . $id;
        $token  = $prefix . '_' . $secret;
        $uuid   = $this->uuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO api_keys (id, tenant_id, name, prefix, hash, scopes, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $uuid,
            $tenantId,
            $name,
            $prefix,
            hash('sha256', $secret),
            json_encode($scopes),
            $expiresAt?->format('Y-m-d H:i:s'),
        ]);

        return ['token' => $token, 'id' => $uuid, 'prefix' => $prefix];
    }

    /**
     * Verifica un token entrante en tiempo constante. Devuelve la fila (con
     * `scopes` ya decodificados) o null si no existe / no coincide / está
     * revocada / expirada.
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $token): ?array
    {
        if (!str_starts_with($token, self::TOKEN_PREFIX)) {
            return null;
        }

        $pos = strrpos($token, '_');
        if ($pos === false) {
            return null;
        }

        $prefix = substr($token, 0, $pos);
        $secret = substr($token, $pos + 1);
        if ($secret === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM api_keys WHERE prefix = ? LIMIT 1');
        $stmt->execute([$prefix]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        if (!hash_equals((string) $row['hash'], hash('sha256', $secret))) {
            return null;
        }

        if ($row['revoked_at'] !== null) {
            return null;
        }

        if ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        $row['scopes'] = $row['scopes'] !== null
            ? (json_decode((string) $row['scopes'], true) ?: [])
            : [];

        return $row;
    }

    public function touch(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function revoke(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_keys SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$id]);
    }

    private function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
