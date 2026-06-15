<?php

namespace App\Support\Cache;

use App\Support\Contracts\CacheInterface;

class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires: int}> */
    private array $store = [];

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }
        return $this->store[$key]['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $this->store[$key] = [
            'value'   => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function has(string $key): bool
    {
        if (!isset($this->store[$key])) {
            return false;
        }
        $expires = $this->store[$key]['expires'];
        if ($expires > 0 && $expires < time()) {
            unset($this->store[$key]);
            return false;
        }
        return true;
    }

    public function available(): bool
    {
        return true;
    }
}
