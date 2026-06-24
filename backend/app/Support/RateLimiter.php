<?php

namespace App\Support;

use App\Support\Cache\ArrayCache;
use App\Support\Contracts\CacheInterface;

class RateLimiter
{
    public function __construct(private CacheInterface $cache = new ArrayCache())
    {
    }

    public function tooManyAttempts(string $key, int $maxAttempts = 5): bool
    {
        $attempts = $this->cache->get($key);
        return $attempts !== null && $attempts >= $maxAttempts;
    }

    public function hit(string $key, int $ttlSeconds = 300): void
    {
        $attempts = $this->cache->get($key) ?? 0;
        $this->cache->set($key, $attempts + 1, $ttlSeconds);
    }

    public function clear(string $key): void
    {
        $this->cache->delete($key);
    }
}
