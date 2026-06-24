<?php

namespace App\Exceptions;

class ForbiddenException extends AppException
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct($message, 403);
    }

    public function getHttpStatusCode(): int
    {
        return 403;
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
