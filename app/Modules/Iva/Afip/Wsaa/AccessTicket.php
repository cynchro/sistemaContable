<?php

namespace App\Modules\Iva\Afip\Wsaa;

use DateTimeImmutable;
use RuntimeException;

/**
 * Ticket de Acceso (TA) devuelto por el WSAA. Contiene el `token` y `sign` que
 * autentican las llamadas a los WS de negocio (WSFEv1, etc.), más la ventana de
 * validez (~12h). Inmutable.
 */
final class AccessTicket
{
    public function __construct(
        public readonly string $token,
        public readonly string $sign,
        public readonly DateTimeImmutable $generationTime,
        public readonly DateTimeImmutable $expirationTime,
    ) {
    }

    /** Parsea el XML `loginTicketResponse` devuelto por loginCms. */
    public static function fromXml(string $xml): self
    {
        $prev = libxml_use_internal_errors(true);
        $doc  = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);

        if ($doc === false || !isset($doc->credentials->token, $doc->credentials->sign, $doc->header->expirationTime)) {
            throw new RuntimeException('TA inválido: no se pudo parsear loginTicketResponse.');
        }

        return new self(
            (string) $doc->credentials->token,
            (string) $doc->credentials->sign,
            new DateTimeImmutable((string) $doc->header->generationTime),
            new DateTimeImmutable((string) $doc->header->expirationTime),
        );
    }

    public function isExpired(int $marginSeconds = 0, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $now->getTimestamp() >= ($this->expirationTime->getTimestamp() - $marginSeconds);
    }
}
