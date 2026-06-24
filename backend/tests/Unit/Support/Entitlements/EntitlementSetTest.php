<?php

namespace Tests\Unit\Support\Entitlements;

use DateTimeImmutable;
use App\Support\Entitlements\Entitlement;
use App\Support\Entitlements\EntitlementSet;
use Tests\Unit\UnitTestCase;

class EntitlementSetTest extends UnitTestCase
{
    public function test_allows_active_flag(): void
    {
        $set = new EntitlementSet(['ia.rag' => new Entitlement('ia.rag', 'flag')]);

        $this->assertTrue($set->allows('ia.rag'));
    }

    public function test_denies_missing_feature(): void
    {
        $set = new EntitlementSet([]);

        $this->assertFalse($set->allows('ia.rag'));
    }

    public function test_denies_disabled_feature(): void
    {
        $set = new EntitlementSet(['ia.rag' => new Entitlement('ia.rag', 'flag', null, false)]);

        $this->assertFalse($set->allows('ia.rag'));
    }

    public function test_denies_expired_feature(): void
    {
        $set = new EntitlementSet([
            'ia.rag' => new Entitlement('ia.rag', 'flag', null, true, null, null, new DateTimeImmutable('2000-01-01')),
        ]);

        $this->assertFalse($set->allows('ia.rag'));
    }

    public function test_allows_not_yet_expired(): void
    {
        $set = new EntitlementSet([
            'ia.rag' => new Entitlement('ia.rag', 'flag', null, true, null, null, new DateTimeImmutable('+1 year')),
        ]);

        $this->assertTrue($set->allows('ia.rag'));
    }

    public function test_limit_and_unlimited(): void
    {
        $set = new EntitlementSet([
            'api.calls' => new Entitlement('api.calls', 'quota', 1000),
            'bots'      => new Entitlement('bots', 'quota', null), // ilimitado
        ]);

        $this->assertSame(1000, $set->limit('api.calls'));
        $this->assertNull($set->limit('bots'));
        $this->assertNull($set->limit('ausente'));
    }

    public function test_remaining_caps_at_zero_and_handles_unlimited(): void
    {
        $set = new EntitlementSet([
            'api.calls' => new Entitlement('api.calls', 'quota', 1000),
            'bots'      => new Entitlement('bots', 'quota', null),
        ]);

        $this->assertSame(700, $set->remaining('api.calls', 300));
        $this->assertSame(0, $set->remaining('api.calls', 1500)); // no negativo
        $this->assertNull($set->remaining('bots', 999));          // ilimitado
        $this->assertNull($set->remaining('ausente', 1));
    }

    public function test_get_returns_entitlement_with_period(): void
    {
        $ent = new Entitlement('api.calls', 'quota', 1000, true, new DateTimeImmutable('2026-06-01'));
        $set = new EntitlementSet(['api.calls' => $ent]);

        $this->assertSame($ent, $set->get('api.calls'));
        $this->assertInstanceOf(DateTimeImmutable::class, $set->get('api.calls')->periodStart);
        $this->assertNull($set->get('ausente'));
    }
}
