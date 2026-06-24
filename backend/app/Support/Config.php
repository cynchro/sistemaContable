<?php

namespace App\Support;

class Config
{
    private static array $cache = [];
    private static string $configPath = '';

    public static function setPath(string $path): void
    {
        self::$configPath = $path;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        [$file, $dotKey] = self::parseKey($key);

        if (!isset(self::$cache[$file])) {
            self::load($file);
        }

        return self::resolveKey(self::$cache[$file], $dotKey, $default);
    }

    public static function all(string $file): array
    {
        if (!isset(self::$cache[$file])) {
            self::load($file);
        }
        return self::$cache[$file];
    }

    private static function load(string $file): void
    {
        $path = self::$configPath . "/{$file}.php";
        self::$cache[$file] = file_exists($path) ? require $path : [];
    }

    private static function parseKey(string $key): array
    {
        $parts = explode('.', $key, 2);
        return [$parts[0], $parts[1] ?? null];
    }

    private static function resolveKey(array $data, ?string $key, mixed $default): mixed
    {
        if ($key === null) {
            return $data;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }
}
