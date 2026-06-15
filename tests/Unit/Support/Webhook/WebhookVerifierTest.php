<?php

namespace Tests\Unit\Support\Webhook;

use App\Support\Request;
use App\Support\Cache\ArrayCache;
use App\Support\Webhook\WebhookVerifier;
use Tests\Unit\UnitTestCase;

class WebhookVerifierTest extends UnitTestCase
{
    private const SECRET  = 'whsec_test_minimum_secret';
    private const PAYLOAD = '{"event":"invoice.paid","id":"in_123"}';

    protected function tearDown(): void
    {
        Request::setTestInputStream(null);
        parent::tearDown();
    }

    private function requestFor(string $payload, string $signatureHeader): Request
    {
        Request::setTestInputStream($payload);
        $_SERVER['CONTENT_TYPE']        = 'application/json';
        $_SERVER['HTTP_X_SIGNATURE']    = $signatureHeader;
        return new Request();
    }

    public function test_sign_then_verify_round_trip(): void
    {
        $cache    = new ArrayCache();
        $verifier = new WebhookVerifier($cache);

        $header  = $verifier->sign(self::PAYLOAD, self::SECRET);
        $request = $this->requestFor(self::PAYLOAD, $header);

        $this->assertStringStartsWith('t=', $header);
        $this->assertStringContainsString(',v1=', $header);
        $this->assertTrue($verifier->verify($request, self::SECRET));
    }

    public function test_rejects_replayed_signature(): void
    {
        $cache    = new ArrayCache();
        $verifier = new WebhookVerifier($cache);
        $header   = $verifier->sign(self::PAYLOAD, self::SECRET);

        $this->assertTrue($verifier->verify($this->requestFor(self::PAYLOAD, $header), self::SECRET));
        // Misma firma, segunda vez → replay.
        $this->assertFalse($verifier->verify($this->requestFor(self::PAYLOAD, $header), self::SECRET));
    }

    public function test_fails_closed_when_replay_store_unavailable(): void
    {
        // Cache no operativo (p. ej. APCu deshabilitado): no podemos detectar
        // reenvíos, así que una firma por lo demás válida debe rechazarse en vez
        // de aceptarse sin protección anti-replay.
        $inertCache = new class extends ArrayCache {
            public function available(): bool
            {
                return false;
            }
        };

        $verifier = new WebhookVerifier($inertCache);
        $header   = $verifier->sign(self::PAYLOAD, self::SECRET);
        $request  = $this->requestFor(self::PAYLOAD, $header);

        $this->assertFalse($verifier->verify($request, self::SECRET));
    }

    public function test_rejects_wrong_secret(): void
    {
        $verifier = new WebhookVerifier(new ArrayCache());
        $header   = $verifier->sign(self::PAYLOAD, self::SECRET);

        $request = $this->requestFor(self::PAYLOAD, $header);
        $this->assertFalse($verifier->verify($request, 'otro-secreto'));
    }

    public function test_rejects_tampered_payload(): void
    {
        $verifier = new WebhookVerifier(new ArrayCache());
        $header   = $verifier->sign(self::PAYLOAD, self::SECRET);

        // El cuerpo cambió respecto al que se firmó.
        $request = $this->requestFor('{"event":"invoice.paid","id":"in_HACKED"}', $header);
        $this->assertFalse($verifier->verify($request, self::SECRET));
    }

    public function test_rejects_stale_timestamp(): void
    {
        $verifier = new WebhookVerifier(new ArrayCache());
        $header   = $verifier->sign(self::PAYLOAD, self::SECRET, time() - 1000);

        $request = $this->requestFor(self::PAYLOAD, $header);
        $this->assertFalse($verifier->verify($request, self::SECRET, 300));
    }

    public function test_rejects_missing_header(): void
    {
        Request::setTestInputStream(self::PAYLOAD);
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $verifier = new WebhookVerifier(new ArrayCache());

        $this->assertFalse($verifier->verify(new Request(), self::SECRET));
    }

    public function test_rejects_malformed_header(): void
    {
        $verifier = new WebhookVerifier(new ArrayCache());
        $request  = $this->requestFor(self::PAYLOAD, 'garbage-without-fields');

        $this->assertFalse($verifier->verify($request, self::SECRET));
    }
}
