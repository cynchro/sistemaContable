<?php

namespace App\Support\Contracts;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl = 0): void;

    public function delete(string $key): void;

    public function has(string $key): bool;

    /**
     * Whether the underlying backend is operational and actually persists data.
     *
     * Features that depend on shared/persisted state for *security* (e.g. the
     * webhook anti-replay store) MUST gate on this: a cache that silently
     * no-ops (such as APCu when the extension is disabled) would turn replay
     * protection off without any error. Such features should fail closed when
     * this returns false rather than trusting an inert cache.
     */
    public function available(): bool;
}
