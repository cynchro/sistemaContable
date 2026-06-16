<?php

namespace Tests\Unit\Modules\Iva\Afip;

use DateTimeImmutable;
use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Afip\Wsaa\WsaaClient;
use App\Modules\Iva\Afip\Wsaa\CmsSigner;
use App\Modules\Iva\Afip\Wsaa\AccessTicket;
use App\Modules\Iva\Afip\Wsaa\TicketStore;
use App\Modules\Iva\Afip\Soap\SoapTransport;

class WsaaClientTest extends UnitTestCase
{
    private function taXml(string $exp): string
    {
        return '<loginTicketResponse version="1.0"><header>'
            . '<generationTime>2026-06-16T10:00:00-03:00</generationTime>'
            . "<expirationTime>{$exp}</expirationTime></header>"
            . '<credentials><token>TK</token><sign>SG</sign></credentials></loginTicketResponse>';
    }

    private function fakeSigner(): CmsSigner
    {
        return new class implements CmsSigner {
            public int $calls = 0;
            public function sign(string $traXml): string
            {
                $this->calls++;
                return 'CMS-FAKE';
            }
        };
    }

    /** Transporte que devuelve un objeto con loginCmsReturn (como ext-soap) y cuenta llamadas. */
    private function fakeTransport(string $xml): SoapTransport
    {
        return new class ($xml) implements SoapTransport {
            public int $calls = 0;
            public function __construct(private string $xml)
            {
            }
            public function call(string $wsdl, string $method, array $args): mixed
            {
                $this->calls++;
                return (object) ['loginCmsReturn' => $this->xml];
            }
        };
    }

    /** TicketStore en memoria (sin DB). */
    private function memoryStore(): TicketStore
    {
        return new class implements TicketStore {
            /** @var array<string, AccessTicket> */
            public array $mem = [];
            public function find(string $cuit, string $service): ?AccessTicket
            {
                return $this->mem["{$cuit}|{$service}"] ?? null;
            }
            public function save(string $cuit, string $service, AccessTicket $ticket): void
            {
                $this->mem["{$cuit}|{$service}"] = $ticket;
            }
        };
    }

    public function test_pide_ta_cuando_no_hay_cache_y_lo_guarda(): void
    {
        $signer    = $this->fakeSigner();
        $transport = $this->fakeTransport($this->taXml('2026-06-16T22:00:00-03:00'));
        $store     = $this->memoryStore();

        $client = new WsaaClient($signer, $transport, $store, 'wsdl://x', '20111111112', 600);
        $ta = $client->authorize('wsfe');

        $this->assertSame('TK', $ta->token);
        $this->assertSame('SG', $ta->sign);
        $this->assertSame(1, $transport->calls);
        $this->assertArrayHasKey('20111111112|wsfe', $store->mem);
    }

    public function test_reusa_ta_vigente_de_cache_sin_llamar_a_afip(): void
    {
        $signer    = $this->fakeSigner();
        $transport = $this->fakeTransport($this->taXml('2026-06-16T22:00:00-03:00'));
        $store     = $this->memoryStore();
        $store->save('20111111112', 'wsfe', new AccessTicket(
            'CACHED',
            'CACHED',
            new DateTimeImmutable('+1 hour'),
            new DateTimeImmutable('+12 hours'),
        ));

        $client = new WsaaClient($signer, $transport, $store, 'wsdl://x', '20111111112', 600);
        $ta = $client->authorize('wsfe');

        $this->assertSame('CACHED', $ta->token);
        $this->assertSame(0, $transport->calls, 'no debe llamar a AFIP si el TA está vigente');
    }

    public function test_renueva_ta_vencido(): void
    {
        $signer    = $this->fakeSigner();
        $transport = $this->fakeTransport($this->taXml('2026-06-16T22:00:00-03:00'));
        $store     = $this->memoryStore();
        $store->save('20111111112', 'wsfe', new AccessTicket(
            'OLD',
            'OLD',
            new DateTimeImmutable('-13 hours'),
            new DateTimeImmutable('-1 hour'), // ya vencido
        ));

        $client = new WsaaClient($signer, $transport, $store, 'wsdl://x', '20111111112', 600);
        $ta = $client->authorize('wsfe');

        $this->assertSame('TK', $ta->token);
        $this->assertSame(1, $transport->calls);
    }
}
