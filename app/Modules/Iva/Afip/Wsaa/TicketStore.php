<?php

namespace App\Modules\Iva\Afip\Wsaa;

/**
 * Cache persistente del Ticket de Acceso (TA) por (cuit, service). El TA debe
 * sobrevivir entre requests/procesos, así que la implementación de referencia es en
 * DB; esta interfaz permite sustituirla (y testear el WsaaClient sin DB).
 */
interface TicketStore
{
    public function find(string $cuit, string $service): ?AccessTicket;

    public function save(string $cuit, string $service, AccessTicket $ticket): void;
}
