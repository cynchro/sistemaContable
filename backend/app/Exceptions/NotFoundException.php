<?php

namespace App\Exceptions;

class NotFoundException extends AppException
{
    public function __construct(string $resource = 'Resource', int|string $id = '')
    {
        $suffix = $id !== '' ? ": {$id}" : '';
        parent::__construct("{$resource} not found{$suffix}", 404);
    }

    public function getHttpStatusCode(): int
    {
        return 404;
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
