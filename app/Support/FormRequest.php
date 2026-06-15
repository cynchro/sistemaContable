<?php

namespace App\Support;

use App\Exceptions\ForbiddenException;
use App\Exceptions\ValidationException;

abstract class FormRequest
{
    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;

        if (!$this->authorize()) {
            throw new ForbiddenException('This action is not authorized.');
        }

        $errors = Validator::validate($this->data, $this->rules());

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    abstract protected function rules(): array;

    protected function authorize(): bool
    {
        return true;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return array_intersect_key($this->data, array_flip(array_keys($this->rules())));
    }

    public function __get(string $key): mixed
    {
        return $this->input($key);
    }
}
