<?php

namespace Tests\Unit\Modules\Iva\Afip;

use DateTimeImmutable;
use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Afip\Wsaa\WsaaClient;
use App\Modules\Iva\Afip\Wsaa\CmsSigner;
use App\Modules\Iva\Afip\Wsaa\AccessTicket;
use App\Modules\Iva\Afip\Wsaa\TicketStore;
use App\Modules\Iva\Afip\Soap\SoapTransport;
use App\Modules\Iva\Afip\Padron\AfipPadronClient;

class AfipPadronClientTest extends UnitTestCase
{
    public function test_consulta_padron_autenticando_y_mapea_persona(): void
    {
        // WSAA con un TA vigente ya cacheado → no necesita firmar ni llamar a loginCms.
        $store = new class implements TicketStore {
            /** @var array<string, AccessTicket> */
            private array $mem = [];
            public function find(string $cuit, string $service): ?AccessTicket
            {
                return $this->mem["{$cuit}|{$service}"] ?? null;
            }
            public function save(string $cuit, string $service, AccessTicket $ticket): void
            {
                $this->mem["{$cuit}|{$service}"] = $ticket;
            }
        };
        $store->save('20999999990', 'ws_sr_padron_a5', new AccessTicket(
            'TK',
            'SG',
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('+11 hours'),
        ));

        $neverSigner = new class implements CmsSigner {
            public function sign(string $traXml): string
            {
                throw new \LogicException('no debería firmar: hay TA vigente');
            }
        };
        $loginTransport = new class implements SoapTransport {
            public function call(string $wsdl, string $method, array $args): mixed
            {
                throw new \LogicException('no debería llamar a loginCms: hay TA vigente');
            }
        };

        $wsaa = new WsaaClient($neverSigner, $loginTransport, $store, 'wsdl://wsaa', '20999999990', 600);

        // Transporte de getPersona que captura los args y devuelve un personaReturn.
        $padronTransport = new class implements SoapTransport {
            /** @var array<string, mixed> */
            public array $lastArgs = [];
            public function call(string $wsdl, string $method, array $args): mixed
            {
                $this->lastArgs = $args;
                return (object) [
                    'personaReturn' => (object) [
                        'datosGenerales' => (object) [
                            'tipoPersona' => 'JURIDICA',
                            'idPersona'   => '30711111118',
                            'razonSocial' => 'ACME SA',
                            'estadoClave' => 'ACTIVO',
                        ],
                    ],
                ];
            }
        };

        $client = new AfipPadronClient($wsaa, $padronTransport, 'wsdl://padron', '20999999990');
        $persona = $client->consultar('30711111118');

        $this->assertSame('30711111118', $persona->cuit);
        $this->assertSame('ACME SA', $persona->denominacion);
        // Pasó el TA y el CUIT consultado correctamente.
        $this->assertSame('TK', $padronTransport->lastArgs['token']);
        $this->assertSame('SG', $padronTransport->lastArgs['sign']);
        $this->assertSame('20999999990', $padronTransport->lastArgs['cuitRepresentada']);
        $this->assertSame('30711111118', $padronTransport->lastArgs['idPersona']);
    }
}
