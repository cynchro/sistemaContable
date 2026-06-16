<?php

namespace Tests\Feature;

use DateTimeImmutable;
use App\Modules\Iva\Afip\Wsaa\AccessTicket;
use App\Modules\Iva\Afip\Wsaa\DbTicketStore;

/**
 * Persistencia del Ticket de Acceso (TA) en la tabla afip_tickets.
 */
class AfipTicketStoreTest extends FeatureTestCase
{
    public function test_guarda_y_recupera_y_hace_upsert(): void
    {
        $store = new DbTicketStore($this->pdo);

        $this->assertNull($store->find('20111111112', 'wsfe'));

        $ta = new AccessTicket(
            'TOKEN-1',
            'SIGN-1',
            new DateTimeImmutable('2026-06-16 10:00:00'),
            new DateTimeImmutable('2026-06-16 22:00:00'),
        );
        $store->save('20111111112', 'wsfe', $ta);

        $found = $store->find('20111111112', 'wsfe');
        $this->assertNotNull($found);
        $this->assertSame('TOKEN-1', $found->token);
        $this->assertSame('SIGN-1', $found->sign);

        // upsert: mismo (cuit, service) reemplaza, no duplica.
        $store->save('20111111112', 'wsfe', new AccessTicket(
            'TOKEN-2',
            'SIGN-2',
            new DateTimeImmutable('2026-06-16 11:00:00'),
            new DateTimeImmutable('2026-06-16 23:00:00'),
        ));

        $found = $store->find('20111111112', 'wsfe');
        $this->assertNotNull($found);
        $this->assertSame('TOKEN-2', $found->token);

        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM afip_tickets WHERE cuit = '20111111112' AND service = 'wsfe'"
        )->fetchColumn();
        $this->assertSame(1, $count);
    }
}
