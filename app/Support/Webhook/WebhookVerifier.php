<?php

namespace App\Support\Webhook;

use App\Support\Request;
use App\Support\Contracts\CacheInterface;
use App\Support\Contracts\WebhookVerifierInterface;

/**
 * Verificación/firmado de webhooks con un esquema propio, liviano y sin deps:
 *
 *   Cabecera `X-Signature: t=<unix_ts>,v1=<hex_hmac_sha256>`
 *   Firma     = HMAC-SHA256( "<ts>.<rawBody>", secret )
 *
 * Endurecimiento:
 *   - ventana de tiempo (`toleranceSeconds`) contra reenvíos tardíos,
 *   - comparación en tiempo constante (`hash_equals`),
 *   - anti-replay por nonce de la firma (CacheInterface, TTL = ventana).
 *
 * Los adaptadores de pasarela (p. ej. modux-billing-stripe) pueden reusar
 * `sign()`/`verify()` o aportar su propio parsing y delegar el HMAC aquí.
 */
class WebhookVerifier implements WebhookVerifierInterface
{
    private const HEADER        = 'X-Signature';
    private const REPLAY_PREFIX = 'webhook:seen:';

    public function __construct(private CacheInterface $cache)
    {
    }

    public function verify(Request $request, string $secret, int $toleranceSeconds = 300): bool
    {
        $header = $request->header(self::HEADER);
        if ($header === null) {
            return false;
        }

        $parts = $this->parseHeader($header);
        if (!isset($parts['t'], $parts['v1'])) {
            return false;
        }

        $timestamp = filter_var($parts['t'], FILTER_VALIDATE_INT);
        if ($timestamp === false) {
            return false;
        }

        // Ventana de tiempo.
        if (abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        // Firma esperada, en tiempo constante.
        $expected = $this->computeSignature((string) $timestamp, $request->rawBody(), $secret);
        if (!hash_equals($expected, $parts['v1'])) {
            return false;
        }

        // Anti-replay: requiere un store que persista. Si el cache no es
        // operativo (p. ej. APCu deshabilitado), no podemos detectar reenvíos
        // → fallamos cerrado en vez de aceptar firmas sin protección.
        if (!$this->cache->available()) {
            return false;
        }

        // La misma firma no se acepta dos veces dentro de la ventana.
        $nonce = self::REPLAY_PREFIX . hash('sha256', $parts['v1']);
        if ($this->cache->has($nonce)) {
            return false;
        }
        $this->cache->set($nonce, 1, $toleranceSeconds);

        return true;
    }

    public function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $ts = $timestamp ?? time();
        $v1 = $this->computeSignature((string) $ts, $payload, $secret);

        return "t={$ts},v1={$v1}";
    }

    private function computeSignature(string $timestamp, string $payload, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    /** @return array<string, string> */
    private function parseHeader(string $header): array
    {
        $out = [];

        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) === 2) {
                $out[trim($kv[0])] = trim($kv[1]);
            }
        }

        return $out;
    }
}
