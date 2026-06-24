<?php

namespace Tests\Unit\Modules\Iva\Afip;

use DateTimeImmutable;
use RuntimeException;
use Tests\Unit\UnitTestCase;
use App\Modules\Iva\Afip\Wsaa\AccessTicket;

class AccessTicketTest extends UnitTestCase
{
    private function sampleXml(string $exp): string
    {
        return <<<XML
        <loginTicketResponse version="1.0">
          <header>
            <source>CN=wsaahomo</source>
            <generationTime>2026-06-16T10:00:00-03:00</generationTime>
            <expirationTime>{$exp}</expirationTime>
          </header>
          <credentials>
            <token>TOKEN-ABC</token>
            <sign>SIGN-XYZ</sign>
          </credentials>
        </loginTicketResponse>
        XML;
    }

    public function test_parsea_token_sign_y_expiracion(): void
    {
        $ta = AccessTicket::fromXml($this->sampleXml('2026-06-16T22:00:00-03:00'));

        $this->assertSame('TOKEN-ABC', $ta->token);
        $this->assertSame('SIGN-XYZ', $ta->sign);
        $this->assertSame('2026-06-16T22:00:00-03:00', $ta->expirationTime->format(DATE_ATOM));
    }

    public function test_xml_invalido_lanza(): void
    {
        $this->expectException(RuntimeException::class);
        AccessTicket::fromXml('<nope/>');
    }

    public function test_is_expired_respeta_margen(): void
    {
        $ta  = AccessTicket::fromXml($this->sampleXml('2026-06-16T22:00:00-03:00'));
        $exp = new DateTimeImmutable('2026-06-16T22:00:00-03:00');

        // 1 hora antes de expirar, sin margen → vigente.
        $this->assertFalse($ta->isExpired(0, $exp->modify('-1 hour')));
        // 1 hora antes, pero con margen de 2h → ya se considera vencido.
        $this->assertTrue($ta->isExpired(7200, $exp->modify('-1 hour')));
        // Pasada la expiración → vencido.
        $this->assertTrue($ta->isExpired(0, $exp->modify('+1 second')));
    }
}
