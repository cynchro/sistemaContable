<?php

namespace App\Modules\Iva\Afip\Padron;

use RuntimeException;
use App\Modules\Iva\Afip\Wsaa\WsaaClient;
use App\Modules\Iva\Afip\Soap\SoapTransport;

/**
 * Cliente del padrón de AFIP (ws_sr_padron_a5 / a13, configurable). Autentica con el WSAA
 * (servicio propio del padrón) y llama a `getPersona(token, sign, cuitRepresentada, idPersona)`
 * — el método es el mismo en A5 y A13; cambian el WSDL, el nombre del servicio y la forma de
 * la respuesta (que normaliza {@see PersonaPadron}).
 */
final class AfipPadronClient implements PadronClient
{
    public function __construct(
        private WsaaClient $wsaa,
        private SoapTransport $transport,
        private string $wsdl,
        private string $cuitRepresentada,
        private string $service = 'ws_sr_padron_a5',
    ) {
    }

    public function consultar(string $cuit): PersonaPadron
    {
        $ta = $this->wsaa->authorize($this->service);

        $response = $this->transport->call($this->wsdl, 'getPersona', [
            'token'            => $ta->token,
            'sign'             => $ta->sign,
            'cuitRepresentada' => $this->cuitRepresentada,
            'idPersona'        => $cuit,
        ]);

        $node = $this->extractPersonaReturn($response);

        return PersonaPadron::fromSoapResponse($node);
    }

    private function extractPersonaReturn(mixed $response): object|array
    {
        if (is_object($response) && isset($response->personaReturn)) {
            return $response->personaReturn;
        }
        if (is_array($response) && isset($response['personaReturn'])) {
            return $response['personaReturn'];
        }
        if (is_object($response) || is_array($response)) {
            return $response;
        }

        throw new RuntimeException('Respuesta inesperada de getPersona (padrón AFIP).');
    }
}
