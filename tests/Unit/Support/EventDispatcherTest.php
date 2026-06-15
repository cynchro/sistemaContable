<?php

namespace Tests\Unit\Support;

use App\Support\EventDispatcher;
use Tests\Unit\UnitTestCase;

class EventDispatcherTest extends UnitTestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function test_dispatches_event_to_listener(): void
    {
        $received = null;

        $this->dispatcher->listen('user.created', function (array $payload) use (&$received) {
            $received = $payload;
        });

        $this->dispatcher->dispatch('user.created', ['id' => 42]);

        $this->assertSame(['id' => 42], $received);
    }

    public function test_multiple_listeners_for_same_event(): void
    {
        $calls = 0;

        $this->dispatcher->listen('order.placed', function () use (&$calls) {
            $calls++;
        });
        $this->dispatcher->listen('order.placed', function () use (&$calls) {
            $calls++;
        });

        $this->dispatcher->dispatch('order.placed');

        $this->assertSame(2, $calls);
    }

    public function test_dispatch_with_no_listeners_does_nothing(): void
    {
        $this->dispatcher->dispatch('unknown.event', ['foo' => 'bar']);
        $this->assertTrue(true); // no exception thrown
    }

    public function test_has_listeners_returns_true_after_registration(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners('my.event'));

        $this->dispatcher->listen('my.event', fn() => null);

        $this->assertTrue($this->dispatcher->hasListeners('my.event'));
    }

    public function test_listeners_for_different_events_are_isolated(): void
    {
        $firedA = false;
        $firedB = false;

        $this->dispatcher->listen('event.a', function () use (&$firedA) {
            $firedA = true;
        });
        $this->dispatcher->listen('event.b', function () use (&$firedB) {
            $firedB = true;
        });

        $this->dispatcher->dispatch('event.a');

        $this->assertTrue($firedA);
        $this->assertFalse($firedB);
    }
}
