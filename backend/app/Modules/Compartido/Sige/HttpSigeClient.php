<?php

namespace App\Modules\Compartido\Sige;

/**
 * Cliente HTTP liviano hacia el SIGE (sistemaCuarto), curl puro (sin SDK ni Guzzle) —
 * mismo patrón que `Cynchro\Billing\Stripe\CurlHttpClient`. Timeout corto: si el SIGE
 * está caído o lento, el alta de empresa en ecosistema no debe quedar trabada.
 */
final class HttpSigeClient implements SigeClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private int $timeout = 5,
    ) {
    }

    public function buscarPorCuit(string $cuit): ?ContribuyenteSige
    {
        $url = rtrim($this->baseUrl, '/') . '/sync/contribuyente/' . $cuit;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'X-API-KEY: ' . $this->apiKey,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new SigeException("No se pudo conectar con el SIGE: {$error}");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response, true) ?: [];

        if ($status >= 400) {
            $message = $data['message'] ?? "El SIGE respondió HTTP {$status}";
            throw new SigeException((string) $message);
        }

        if (!($data['encontrado'] ?? false)) {
            return null;
        }

        return ContribuyenteSige::fromArray($data);
    }
}
