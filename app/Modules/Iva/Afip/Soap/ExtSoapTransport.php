<?php

namespace App\Modules\Iva\Afip\Soap;

use RuntimeException;
use SoapClient;
use SoapFault;

/**
 * Implementación de SoapTransport sobre la extensión nativa `ext-soap`.
 * Cachea un SoapClient por WSDL. Traduce SoapFault a RuntimeException.
 */
final class ExtSoapTransport implements SoapTransport
{
    /** @var array<string, SoapClient> */
    private array $clients = [];

    /** @param array<string, mixed> $options opciones extra para SoapClient */
    public function __construct(private array $options = [])
    {
    }

    public function call(string $wsdl, string $method, array $args): mixed
    {
        if (!class_exists(SoapClient::class)) {
            throw new RuntimeException('La extensión php-soap no está instalada.');
        }

        try {
            return $this->client($wsdl)->__soapCall($method, [$args]);
        } catch (SoapFault $e) {
            throw new RuntimeException("Fallo SOAP en {$method}: {$e->getMessage()}", 0, $e);
        }
    }

    private function client(string $wsdl): SoapClient
    {
        return $this->clients[$wsdl] ??= new SoapClient($wsdl, array_merge([
            'soap_version' => SOAP_1_2,
            'exceptions'   => true,
            'trace'        => true,
            'cache_wsdl'   => WSDL_CACHE_NONE,
        ], $this->options));
    }
}
