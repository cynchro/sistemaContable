<?php

namespace App\Exceptions;

class AuthException extends AppException
{
    public function __construct(string $message = 'Unauthenticated', int $code = 401)
    {
        parent::__construct($message, $code);
    }

    public function getHttpStatusCode(): int
    {
        return $this->getCode();
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
