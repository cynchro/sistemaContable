<?php

namespace Tests\Unit\Support;

use App\Support\Logger;
use Tests\Unit\UnitTestCase;

/**
 * Logger: el driver `stderr` debe escribir por un stream portable (no por la
 * constante `STDERR`, que solo existe en CLI y tumba la request bajo SAPI web),
 * el driver `file` debe persistir, y el filtrado por nivel debe descartar lo que
 * esté por debajo del mínimo.
 */
class LoggerTest extends UnitTestCase
{
    public function test_stderr_channel_writes_via_portable_stream(): void
    {
        $logger = $this->capturingLogger('debug');

        $logger->warning('hola mundo', ['k' => 'v']);

        $out = $logger->captured();
        $this->assertStringContainsString('"level":"warning"', $out);
        $this->assertStringContainsString('"message":"hola mundo"', $out);
        $this->assertStringContainsString('"k":"v"', $out);
    }

    public function test_level_filtering_drops_below_minimum(): void
    {
        $logger = $this->capturingLogger('error');

        $logger->info('no debería registrarse');
        $logger->error('esto sí');

        $out = $logger->captured();
        $this->assertStringNotContainsString('no debería registrarse', $out);
        $this->assertStringContainsString('esto sí', $out);
    }

    public function test_file_channel_writes_entry(): void
    {
        $path = sys_get_temp_dir() . '/modux_logger_test_' . bin2hex(random_bytes(4)) . '.log';

        $logger = new Logger([
            'default'  => 'file',
            'level'    => 'debug',
            'channels' => ['file' => ['driver' => 'file', 'path' => $path]],
        ]);

        try {
            $logger->error('algo falló');

            $this->assertFileExists($path);
            $contents = (string) file_get_contents($path);
            $this->assertStringContainsString('"level":"error"', $contents);
            $this->assertStringContainsString('"message":"algo falló"', $contents);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Logger que captura el "stderr" en un stream en memoria, sin depender de la
     * constante STDERR — exactamente la portabilidad que arregla el bug bajo web.
     */
    private function capturingLogger(string $level): Logger
    {
        $config = [
            'default'  => 'stderr',
            'level'    => $level,
            'channels' => ['stderr' => ['driver' => 'stderr']],
        ];

        return new class ($config) extends Logger {
            /** @var resource */
            private $buffer;

            /** @param array<string, mixed> $config */
            public function __construct(array $config)
            {
                parent::__construct($config);
                $this->buffer = fopen('php://memory', 'w+b');
            }

            public function captured(): string
            {
                rewind($this->buffer);
                return (string) stream_get_contents($this->buffer);
            }

            /** @return resource */
            protected function stderrStream()
            {
                return $this->buffer;
            }
        };
    }
}
