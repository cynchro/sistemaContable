<?php

namespace App\Exceptions;

class DatabaseException extends AppException
{
    public function getHttpStatusCode(): int
    {
        return 500;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success' => false,
            'message' => 'A database error occurred.',
        ];
    }
}
