<?php

namespace App\Exceptions;

/**
 * El tenant agotó la cuota de una feature en el ciclo vigente. 429, con
 * `Retry-After` (segundos hasta el reset del ciclo) cuando se conoce.
 */
class QuotaExceededException extends AppException
{
    public function __construct(string $message = 'Quota exceeded', private ?int $retryAfter = null)
    {
        parent::__construct($message, 429);
    }

    public function getHttpStatusCode(): int
    {
        return 429;
    }

    /** Segundos hasta el reset del ciclo, o null si no se conoce. */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success' => false,
            'message' => $this->getMessage(),
        ];
    }
}
