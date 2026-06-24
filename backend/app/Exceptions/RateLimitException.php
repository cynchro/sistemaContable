<?php

namespace App\Exceptions;

class RateLimitException extends AppException
{
    public function __construct(string $message = 'Too many attempts. Please try again later.')
    {
        parent::__construct($message, 429);
    }

    public function getHttpStatusCode(): int
    {
        return 429;
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
