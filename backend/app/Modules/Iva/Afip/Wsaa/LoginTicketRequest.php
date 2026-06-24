<?php

namespace App\Modules\Iva\Afip\Wsaa;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Ticket de Requerimiento de Acceso (TRA) del WSAA.
 *
 * Es el XML que se firma con el certificado y se envía a `loginCms`. Contiene un
 * `uniqueId` y una ventana de validez (generationTime / expirationTime). AFIP
 * rechaza un TRA con `uniqueId`/ventana ya usados, por eso el id se deriva del
 * timestamp y la ventana es corta.
 */
final class LoginTicketRequest
{
    private DateTimeImmutable $now;

    public function __construct(
        private string $service,
        ?DateTimeImmutable $now = null,
        private int $windowSeconds = 600,
    ) {
        $this->now = $now ?? new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
    }

    public function uniqueId(): int
    {
        return $this->now->getTimestamp();
    }

    public function toXml(): string
    {
        $generation = $this->now->modify("-{$this->windowSeconds} seconds");
        $expiration = $this->now->modify("+{$this->windowSeconds} seconds");

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $root = $dom->createElement('loginTicketRequest');
        $root->setAttribute('version', '1.0');
        $dom->appendChild($root);

        $header = $dom->createElement('header');
        $root->appendChild($header);
        $header->appendChild($dom->createElement('uniqueId', (string) $this->uniqueId()));
        $header->appendChild($dom->createElement('generationTime', $generation->format(DATE_ATOM)));
        $header->appendChild($dom->createElement('expirationTime', $expiration->format(DATE_ATOM)));

        $root->appendChild($dom->createElement('service', $this->service));

        return (string) $dom->saveXML();
    }
}
