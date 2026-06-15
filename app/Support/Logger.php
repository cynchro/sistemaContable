<?php

namespace App\Support;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class Logger implements LoggerInterface
{
    use LoggerTrait;

    private string $channel;
    private array $channelConfig;
    private string $minLevel;

    private const LEVELS = [
        'debug'     => 0,
        'info'      => 1,
        'notice'    => 2,
        'warning'   => 3,
        'error'     => 4,
        'critical'  => 5,
        'alert'     => 6,
        'emergency' => 7,
    ];

    public function __construct(array $config)
    {
        $this->channel       = $config['default'] ?? 'file';
        $this->minLevel      = $config['level'] ?? 'debug';
        $this->channelConfig = $config['channels'][$this->channel] ?? ['driver' => 'stderr'];
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$this->minLevel] ?? 0)) {
            return;
        }

        $entry = json_encode([
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'level'     => $level,
            'message'   => (string) $message,
            'context'   => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        match ($this->channelConfig['driver'] ?? 'stderr') {
            'file'   => $this->writeFile($this->channelConfig['path'] ?? '/tmp/app.log', $entry),
            'stderr' => $this->writeStderr($entry),
            default  => null,
        };
    }

    private function writeFile(string $path, string $entry): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->writeStderr($entry);
            return;
        }
        if (@file_put_contents($path, $entry . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            $this->writeStderr($entry);
        }
    }

    private function writeStderr(string $entry): void
    {
        $stream = $this->stderrStream();
        if (is_resource($stream)) {
            fwrite($stream, $entry . PHP_EOL);
        }
    }

    /**
     * Stream de STDERR portable entre SAPIs. La constante `STDERR` SOLO existe
     * bajo CLI; en SAPI web (mod_php / php-fpm) referenciarla lanza un Error y
     * tumba la request. `php://stderr` funciona en todos los SAPIs. Protegido
     * para poder sobrescribirlo en tests.
     *
     * @return resource|false
     */
    protected function stderrStream()
    {
        return defined('STDERR') ? STDERR : fopen('php://stderr', 'wb');
    }
}
