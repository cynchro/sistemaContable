<?php

namespace App\Exceptions;

/**
 * El tenant no tiene habilitada una feature que se obtiene comprando/mejorando
 * el plan. 402 es una señal accionable para el front ("actualizá tu plan"),
 * distinta de 403 (scope/permiso insuficiente).
 */
class PaymentRequiredException extends AppException
{
    public function __construct(string $message = 'Payment required')
    {
        parent::__construct($message, 402);
    }

    public function getHttpStatusCode(): int
    {
        return 402;
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
