<?php

namespace App\Exceptions;

/**
 * Violación de una regla de negocio sobre el estado actual del recurso
 * (p. ej. borrar un período cerrado). Mapea a HTTP 409 Conflict.
 */
class ConflictException extends AppException
{
    public function __construct(string $message = 'Conflict')
    {
        parent::__construct($message, 409);
    }

    public function getHttpStatusCode(): int
    {
        return 409;
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
