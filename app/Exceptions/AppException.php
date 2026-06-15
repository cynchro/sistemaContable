<?php

namespace App\Exceptions;

abstract class AppException extends \RuntimeException
{
    abstract public function getHttpStatusCode(): int;

    /** @return array<string, mixed> */
    abstract public function toArray(): array;
}
