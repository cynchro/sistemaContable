<?php

namespace App\Exceptions;

class ValidationException extends AppException
{
    /** @var array<string, list<string>> */
    private array $errors;

    /** @param array<string, list<string>> $errors */
    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message, 422);
        $this->errors = $errors;
    }

    /** @return array<string, list<string>> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getHttpStatusCode(): int
    {
        return 422;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success' => false,
            'message' => $this->getMessage(),
            'errors'  => $this->errors,
        ];
    }
}
