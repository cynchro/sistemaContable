<?php

namespace Tests\Unit\Support;

use PDO;
use Tests\Unit\UnitTestCase;

/**
 * `config/database.php` expone las conexiones persistentes de PDO vía la env
 * `DB_PERSISTENT` (opt-in, false por defecto).
 */
class DatabaseConfigTest extends UnitTestCase
{
    private function loadConfig(): array
    {
        return require dirname(__DIR__, 3) . '/config/database.php';
    }

    public function test_persistent_is_off_by_default(): void
    {
        unset($_ENV['DB_PERSISTENT']);

        $cfg = $this->loadConfig();

        $this->assertFalse($cfg['options'][PDO::ATTR_PERSISTENT]);
    }

    public function test_persistent_can_be_enabled_via_env(): void
    {
        $_ENV['DB_PERSISTENT'] = 'true';

        try {
            $cfg = $this->loadConfig();
            $this->assertTrue($cfg['options'][PDO::ATTR_PERSISTENT]);
        } finally {
            unset($_ENV['DB_PERSISTENT']);
        }
    }
}
