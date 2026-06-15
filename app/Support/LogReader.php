<?php

namespace App\Support;

class LogReader
{
    private string $logFile;

    /** @var array<int, array<string, mixed>> */
    private array $logs      = [];
    private int $logsPerPage = 15;
    private int $totalLogs   = 0;
    private int $totalPages  = 0;
    private bool $loaded     = false;

    public function __construct(?string $logPath = null)
    {
        $this->logFile = $logPath ?? dirname(__DIR__, 3) . '/storage/logs/app.log';
    }

    private function ensureLoaded(): void
    {
        if (!$this->loaded) {
            $this->loadLogs();
            $this->loaded = true;
        }
    }

    private function loadLogs(): void
    {
        if (!file_exists($this->logFile)) {
            $this->totalLogs  = 0;
            $this->totalPages = 0;
            return;
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach (array_reverse($lines) as $line) {
            $entry = $this->parseLine($line);
            if ($entry !== null) {
                $this->logs[] = $entry;
            }
        }

        $this->totalLogs  = count($this->logs);
        $this->totalPages = (int) ceil($this->totalLogs / $this->logsPerPage);
    }

    /** @return array<string, mixed>|null */
    private function parseLine(string $line): ?array
    {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            return [
                'date'         => $decoded['timestamp'] ?? '',
                'level'        => strtoupper($decoded['level'] ?? 'INFO'),
                'message'      => $decoded['message'] ?? '',
                'context'      => $decoded['context'] ?? [],
                'full_message' => $line,
                'format'       => 'json',
            ];
        }

        if (preg_match('/^\[(.*?)\] \[(.*?)\]/', $line, $m)) {
            return [
                'date'         => $m[1],
                'level'        => $m[2],
                'message'      => strlen($line) > 100 ? substr($line, 0, 100) . '...' : $line,
                'context'      => [],
                'full_message' => $line,
                'format'       => 'text',
            ];
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getPaginatedLogs(int $page = 1): array
    {
        $this->ensureLoaded();
        $start = ($page - 1) * $this->logsPerPage;
        return array_slice($this->logs, $start, $this->logsPerPage);
    }

    /** @return array<string, int> */
    public function getPaginationData(): array
    {
        $this->ensureLoaded();
        return [
            'totalLogs'  => $this->totalLogs,
            'totalPages' => $this->totalPages,
        ];
    }

    /** @return array<string, mixed>|null */
    public function getLogDetail(int $index): ?array
    {
        $this->ensureLoaded();
        $log = $this->logs[$index] ?? null;

        if ($log === null) {
            return null;
        }

        if ($log['format'] === 'json') {
            $ctx = $log['context'];
            return array_merge($log, [
                'exception'   => $ctx['exception'] ?? null,
                'file'        => $ctx['file'] ?? null,
                'line'        => $ctx['line'] ?? null,
                'stack_trace' => $ctx['trace'] ?? null,
                'ip'          => $ctx['ip'] ?? null,
                'uri'         => $ctx['uri'] ?? null,
                'method'      => $ctx['method'] ?? null,
                'duration_ms' => $ctx['duration_ms'] ?? null,
            ]);
        }

        return array_merge($log, $this->parseLegacyMessage($log['full_message']));
    }

    /** @return array<string, mixed> */
    private function parseLegacyMessage(string $text): array
    {
        $out = [
            'ip'          => null,
            'stack_trace' => null,
            'file'        => null,
            'line'        => null,
            'user_id'     => null,
            'url'         => null,
        ];

        if (preg_match('/IP Address:\s*(.*)/', $text, $m)) {
            $out['ip'] = trim($m[1]);
        }
        if (preg_match('/Stack Trace:\s*(.*?)(?=\nInput Data:|\nUser ID:|$)/s', $text, $m)) {
            $out['stack_trace'] = trim($m[1]);
        }
        if (preg_match('/Archivo:\s*(.*)/', $text, $m)) {
            $out['file'] = trim($m[1]);
        }
        if (preg_match('/Línea:\s*(.*)/', $text, $m)) {
            $out['line'] = trim($m[1]);
        }
        if (preg_match('/User ID:\s*(.*)/', $text, $m)) {
            $out['user_id'] = trim($m[1]);
        }
        if (preg_match('/URL:\s*(http[s]?:\/\/[^\s]+)/', $text, $m)) {
            $out['url'] = trim($m[1]);
        }

        return $out;
    }

    public function deleteAllLogs(): void
    {
        file_put_contents($this->logFile, '');
        $this->logs       = [];
        $this->totalLogs  = 0;
        $this->totalPages = 0;
    }
}
