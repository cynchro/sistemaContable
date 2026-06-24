<?php

namespace App\Support\Cache;

use App\Support\Contracts\CacheInterface;

class ApcuCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        if (!function_exists('apcu_fetch')) {
            return null;
        }
        $success = false;
        $value   = apcu_fetch($key, $success);
        return $success ? $value : null;
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        if (function_exists('apcu_store')) {
            apcu_store($key, $value, $ttl);
        }
    }

    public function delete(string $key): void
    {
        if (function_exists('apcu_delete')) {
            apcu_delete($key);
        }
    }

    public function has(string $key): bool
    {
        if (!function_exists('apcu_fetch')) {
            return false;
        }
        $success = false;
        apcu_fetch($key, $success);
        return $success;
    }

    /**
     * APCu is operational only when the extension is loaded *and* enabled for
     * the current SAPI. Note `apc.enable_cli=0` (the default) disables it under
     * CLI, and APCu is per-process — it is not shared across hosts/containers,
     * so a horizontally-scaled deploy needs a shared store for anti-replay.
     */
    public function available(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }
}
