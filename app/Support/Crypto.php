<?php

namespace App\Support;

use RuntimeException;

/**
 * Cifrado simétrico reversible para secretos guardados en reposo (AES-256-GCM).
 *
 * Pensado para credenciales de portales de TERCEROS que el estudio necesita poder
 * recuperar en claro (p. ej. el usuario/clave del portal de AFIP de un cliente).
 * NO usar para contraseñas de login propias del sistema: ésas van con password_hash
 * (un solo sentido, no recuperable). Ver App\Modules\Auth.
 *
 * Formato del payload: "enc:v1:" . base64(iv[12] . tag[16] . ciphertext).
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'enc:v1:';
    private const IV_LEN = 12;  // nonce de 96 bits, recomendado para GCM
    private const TAG_LEN = 16;

    public static function encrypt(string $plaintext): string
    {
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new RuntimeException('No se pudo cifrar el valor.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): string
    {
        if (!self::isEncrypted($payload)) {
            return $payload; // tolera valores guardados en claro antes de activar el cifrado
        }

        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) <= self::IV_LEN + self::TAG_LEN) {
            throw new RuntimeException('Payload cifrado inválido.');
        }

        $iv         = substr($raw, 0, self::IV_LEN);
        $tag        = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            throw new RuntimeException('No se pudo descifrar el valor (clave incorrecta o dato corrupto).');
        }

        return $plaintext;
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /** Deriva una clave de 32 bytes desde APP_ENCRYPTION_KEY (acepta cualquier passphrase). */
    private static function key(): string
    {
        $key = Config::get('app.encryption_key');

        if (!$key) {
            throw new RuntimeException('APP_ENCRYPTION_KEY no está configurada. Agregala en tu .env.');
        }

        return hash('sha256', (string) $key, true);
    }
}
