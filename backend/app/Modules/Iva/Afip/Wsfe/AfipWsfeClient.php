<?php

namespace App\Modules\Iva\Afip\Wsfe;

use RuntimeException;
use App\Modules\Iva\Afip\Wsaa\WsaaClient;
use App\Modules\Iva\Afip\Soap\SoapTransport;

/**
 * Cliente WSFEv1 sobre SOAP. Se autentica con el WSAA (servicio 'wsfe') e inyecta el
 * bloque Auth{Token, Sign, Cuit} en cada llamada.
 */
final class AfipWsfeClient implements WsfeClient
{
    private const SERVICE = 'wsfe';

    public function __construct(
        private WsaaClient $wsaa,
        private SoapTransport $transport,
        private string $wsdl,
        private string $cuit,
    ) {
    }

    public function dummy(): array
    {
        $r = $this->transport->call($this->wsdl, 'FEDummy', []);
        $res = self::get($r, 'FEDummyResult') ?? $r;

        return [
            'appserver'  => (string) (self::get($res, 'AppServer') ?? ''),
            'dbserver'   => (string) (self::get($res, 'DbServer') ?? ''),
            'authserver' => (string) (self::get($res, 'AuthServer') ?? ''),
        ];
    }

    public function ultimoAutorizado(int $ptoVta, int $cbteTipo): int
    {
        $r = $this->transport->call($this->wsdl, 'FECompUltimoAutorizado', [
            'Auth'     => $this->auth(),
            'PtoVta'   => $ptoVta,
            'CbteTipo' => $cbteTipo,
        ]);

        $res = self::get($r, 'FECompUltimoAutorizadoResult') ?? $r;

        return (int) (self::get($res, 'CbteNro') ?? 0);
    }

    public function solicitarCae(array $feCaeReq): ComprobanteCae
    {
        $r = $this->transport->call($this->wsdl, 'FECAESolicitar', [
            'Auth'     => $this->auth(),
            'FeCAEReq' => $feCaeReq,
        ]);

        return ComprobanteCae::fromSoapResponse(self::get($r, 'FECAESolicitarResult') ?? $r);
    }

    public function consultarComprobante(int $ptoVta, int $cbteTipo, int $cbteNro): ComprobanteConsultado
    {
        $r = $this->transport->call($this->wsdl, 'FECompConsultar', [
            'Auth'          => $this->auth(),
            'FeCompConsReq' => ['CbteTipo' => $cbteTipo, 'CbteNro' => $cbteNro, 'PtoVta' => $ptoVta],
        ]);

        return ComprobanteConsultado::fromSoapResponse(self::get($r, 'FECompConsultarResult') ?? $r);
    }

    /** @return array{Token:string, Sign:string, Cuit:string} */
    private function auth(): array
    {
        if ($this->cuit === '') {
            throw new RuntimeException('AFIP_CUIT no configurado (requerido por WSFEv1).');
        }

        $ta = $this->wsaa->authorize(self::SERVICE);

        return ['Token' => $ta->token, 'Sign' => $ta->sign, 'Cuit' => $this->cuit];
    }

    private static function get(mixed $node, string $key): mixed
    {
        if (is_object($node)) {
            return $node->{$key} ?? null;
        }
        if (is_array($node)) {
            return $node[$key] ?? null;
        }

        return null;
    }
}
