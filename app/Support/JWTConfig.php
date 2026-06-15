<?php

namespace App\Support;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTConfig
{
    private static function secretKey(): string
    {
        $key = Config::get('auth.jwt_secret');

        if (!$key) {
            throw new \RuntimeException('JWT_SECRET is not configured. Set it in your .env file.');
        }

        return $key;
    }

    private static function algorithm(): string
    {
        return Config::get('auth.jwt_algo', 'HS256');
    }

    private static function lifetime(): int
    {
        return Config::get('auth.jwt_ttl', 86400);
    }

    private static function refreshLifetime(): int
    {
        return Config::get('auth.jwt_refresh_ttl', 2592000); // 30 days
    }

    public static function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function refreshExpiresAt(): string
    {
        return date('Y-m-d H:i:s', time() + self::refreshLifetime());
    }

    public static function generateToken(int|string $userId, ?string $tenantId = null): string
    {
        $payload = [
            'iss' => Config::get('auth.jwt_issuer', 'monolito-modular'),
            'iat' => time(),
            'exp' => time() + self::lifetime(),
            'sub' => $userId,
        ];

        if ($tenantId !== null) {
            $payload['tenant_id'] = $tenantId;
        }

        return JWT::encode($payload, self::secretKey(), self::algorithm());
    }

    public static function decodeToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::secretKey(), self::algorithm()));
            return (array) $decoded;
        } catch (\UnexpectedValueException | \InvalidArgumentException) {
            return null;
        }
    }
}
