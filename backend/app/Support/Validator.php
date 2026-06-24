<?php

namespace App\Support;

use App\Exceptions\ValidationException;

class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /**
     * @param array<string, mixed>            $data
     * @param array<string, string|list<string>> $rules
     * @return array<string, list<string>>
     */
    public static function validate(array $data, array $rules): array
    {
        $instance = new self();

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;

            $isNullable = in_array('nullable', $ruleList, true);
            $value      = $data[$field] ?? null;

            if ($isNullable && ($value === null || $value === '')) {
                continue;
            }

            foreach ($ruleList as $rule) {
                $instance->applyRule($data, $field, (string) $rule);
            }
        }

        return $instance->errors;
    }

    /**
     * @param array<string, mixed>            $data
     * @param array<string, string|list<string>> $rules
     * @return array<string, mixed>
     */
    public static function validateOrFail(array $data, array $rules): array
    {
        $errors = static::validate($data, $rules);

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function applyRule(array $data, string $field, string $rule): void
    {
        $value = $data[$field] ?? null;

        [$ruleName, $ruleParam] = str_contains($rule, ':')
            ? explode(':', $rule, 2)
            : [$rule, null];

        switch ($ruleName) {
            case 'required':
                if ($value === null || $value === '') {
                    $this->addError($field, "{$field} is required.");
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$field} must be a valid email address.");
                }
                break;

            case 'min':
                if ($value !== null && mb_strlen((string) $value) < (int) $ruleParam) {
                    $this->addError($field, "{$field} must be at least {$ruleParam} characters.");
                }
                break;

            case 'max':
                if ($value !== null && mb_strlen((string) $value) > (int) $ruleParam) {
                    $this->addError($field, "{$field} must be no more than {$ruleParam} characters.");
                }
                break;

            case 'integer':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "{$field} must be an integer.");
                }
                break;

            case 'boolean':
                if ($value !== null && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                    $this->addError($field, "{$field} must be boolean.");
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $ruleParam);
                if ($value !== null && !in_array((string) $value, $allowed, true)) {
                    $this->addError($field, "{$field} must be one of: {$ruleParam}.");
                }
                break;

            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->addError($field, "{$field} must be a number.");
                }
                break;

            case 'confirmed':
                $confirmation = $data["{$field}_confirmation"] ?? null;
                if ($value !== $confirmation) {
                    $this->addError($field, "{$field} confirmation does not match.");
                }
                break;

            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->addError($field, "{$field} must be a string.");
                }
                break;

            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->addError($field, "{$field} must be an array.");
                }
                break;

            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "{$field} must be a valid URL.");
                }
                break;

            case 'date':
                if ($value !== null && $value !== '') {
                    $format = $ruleParam ?? 'Y-m-d';
                    $parsed = \DateTimeImmutable::createFromFormat($format, (string) $value);
                    if (!$parsed || $parsed->format($format) !== (string) $value) {
                        $this->addError($field, "{$field} must be a valid date ({$format}).");
                    }
                }
                break;

            case 'regex':
                if ($value !== null && $value !== '' && $ruleParam !== null) {
                    if (@preg_match($ruleParam, (string) $value) !== 1) {
                        $this->addError($field, "{$field} format is invalid.");
                    }
                }
                break;

            case 'uuid':
                $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
                if ($value !== null && $value !== '' && !preg_match($uuidPattern, (string) $value)) {
                    $this->addError($field, "{$field} must be a valid UUID.");
                }
                break;

            case 'trim':
            case 'nullable':
                break;
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
