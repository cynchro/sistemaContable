<?php

namespace App\Support;

class EventDispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(string $event, array $payload = []): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }
}
