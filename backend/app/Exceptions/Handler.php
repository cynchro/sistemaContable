<?php

namespace App\Exceptions;

use App\Support\Logger;
use App\Support\Config;
use App\Exceptions\MethodNotAllowedException;
use Dotenv\Exception\ValidationException as DotenvValidationException;

class Handler
{
    public function __construct(private Logger $logger)
    {
    }

    public function handle(\Throwable $e): void
    {
        $status = $this->resolveStatus($e);

        if ($status >= 500) {
            $this->logger->error($e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
        } else {
            $this->logger->warning($e->getMessage(), [
                'exception' => get_class($e),
                'status'    => $status,
            ]);
        }

        $body = $this->resolveBody($e);

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        if ($e instanceof MethodNotAllowedException) {
            header('Allow: ' . implode(', ', $e->getAllowedMethods()));
        }

        if ($e instanceof QuotaExceededException && $e->getRetryAfter() !== null) {
            header('Retry-After: ' . $e->getRetryAfter());
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    private function resolveStatus(\Throwable $e): int
    {
        if ($e instanceof DotenvValidationException) {
            return 422;
        }
        if ($e instanceof AppException) {
            return $e->getHttpStatusCode();
        }
        return 500;
    }

    /** @return array<string, mixed> */
    private function resolveBody(\Throwable $e): array
    {
        if ($e instanceof AppException) {
            return $e->toArray();
        }

        if ($e instanceof DotenvValidationException) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $debug = Config::get('app.debug', false);

        return [
            'success' => false,
            'message' => $debug ? $e->getMessage() : 'An internal server error occurred.',
            'debug'   => $debug ? [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ] : null,
        ];
    }

    public static function register(Logger $logger): void
    {
        $instance = new self($logger);

        set_exception_handler([$instance, 'handle']);

        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function () use ($logger): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $logger->error('Fatal error', $error);
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(
                    ['success' => false, 'message' => 'An internal server error occurred.'],
                    JSON_UNESCAPED_UNICODE
                );
            }
        });
    }
}
