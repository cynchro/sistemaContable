<?php

namespace Tests\Unit\Modules\Iva\Afip;

use DateTimeImmutable;
use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Afip\Wsaa\LoginTicketRequest;

class LoginTicketRequestTest extends UnitTestCase
{
    public function test_genera_xml_valido_con_servicio_y_ventana(): void
    {
        $now = new DateTimeImmutable('2026-06-16T10:00:00-03:00');
        $xml = (new LoginTicketRequest('wsfe', $now, 600))->toXml();

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        $this->assertSame('wsfe', (string) $doc->service);
        $this->assertSame((string) $now->getTimestamp(), (string) $doc->header->uniqueId);

        $gen = new DateTimeImmutable((string) $doc->header->generationTime);
        $exp = new DateTimeImmutable((string) $doc->header->expirationTime);
        $this->assertLessThan($exp->getTimestamp(), $gen->getTimestamp());
        // La ventana es ±600s alrededor de "now".
        $this->assertSame($now->getTimestamp() - 600, $gen->getTimestamp());
        $this->assertSame($now->getTimestamp() + 600, $exp->getTimestamp());
    }
}
