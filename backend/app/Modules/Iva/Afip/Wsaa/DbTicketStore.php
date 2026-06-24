<?php

namespace App\Modules\Iva\Afip\Wsaa;

use PDO;
use DateTimeImmutable;

/**
 * TicketStore respaldado en la tabla `afip_tickets` (un TA por cuit+service).
 */
class DbTicketStore implements TicketStore
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(string $cuit, string $service): ?AccessTicket
    {
        $stmt = $this->pdo->prepare(
            'SELECT token, sign, generation_time, expiration_time
             FROM afip_tickets WHERE cuit = ? AND service = ?'
        );
        $stmt->execute([$cuit, $service]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new AccessTicket(
            (string) $row['token'],
            (string) $row['sign'],
            new DateTimeImmutable((string) $row['generation_time']),
            new DateTimeImmutable((string) $row['expiration_time']),
        );
    }

    public function save(string $cuit, string $service, AccessTicket $ticket): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO afip_tickets (cuit, service, token, sign, generation_time, expiration_time)
             VALUES (:cuit, :service, :token, :sign, :gen, :exp)
             ON DUPLICATE KEY UPDATE
                token = VALUES(token), sign = VALUES(sign),
                generation_time = VALUES(generation_time), expiration_time = VALUES(expiration_time)'
        );
        $stmt->execute([
            'cuit'    => $cuit,
            'service' => $service,
            'token'   => $ticket->token,
            'sign'    => $ticket->sign,
            'gen'     => $ticket->generationTime->format('Y-m-d H:i:s'),
            'exp'     => $ticket->expirationTime->format('Y-m-d H:i:s'),
        ]);
    }
}
