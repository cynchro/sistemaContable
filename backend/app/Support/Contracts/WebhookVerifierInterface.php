<?php

namespace App\Support\Contracts;

use App\Support\Request;

interface WebhookVerifierInterface
{
    /**
     * Verifica la firma de un webhook entrante:
     *   - firma HMAC-SHA256 sobre `<timestamp>.<rawBody>`,
     *   - dentro de la ventana de tiempo (`toleranceSeconds`),
     *   - no reutilizada (anti-replay vía CacheInterface).
     *
     * Devuelve true solo si las tres condiciones se cumplen.
     */
    public function verify(Request $request, string $secret, int $toleranceSeconds = 300): bool;

    /**
     * Genera el valor de cabecera de firma para un webhook saliente:
     * `t=<timestamp>,v1=<hmac>`.
     */
    public function sign(string $payload, string $secret, ?int $timestamp = null): string;
}
