<?php

namespace App\Exceptions;

class MethodNotAllowedException extends AppException
{
    /** @param list<string> $allowedMethods */
    public function __construct(private array $allowedMethods)
    {
        parent::__construct('Method Not Allowed.', 405);
    }

    public function getHttpStatusCode(): int
    {
        return 405;
    }

    /** @return list<string> */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success'         => false,
            'message'         => $this->getMessage(),
            'allowed_methods' => $this->allowedMethods,
        ];
    }
}
