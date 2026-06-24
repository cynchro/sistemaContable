<?php

namespace Tests\Unit\Support;

use App\Support\Validator;
use App\Exceptions\ValidationException;
use Tests\Unit\UnitTestCase;

class ValidatorTest extends UnitTestCase
{
    public function test_required_rule_fails_on_empty_value(): void
    {
        $errors = Validator::validate(['name' => ''], ['name' => 'required']);
        $this->assertArrayHasKey('name', $errors);
    }

    public function test_required_rule_fails_on_missing_key(): void
    {
        $errors = Validator::validate([], ['name' => 'required']);
        $this->assertArrayHasKey('name', $errors);
    }

    public function test_required_rule_passes_with_value(): void
    {
        $errors = Validator::validate(['name' => 'John'], ['name' => 'required']);
        $this->assertEmpty($errors);
    }

    public function test_email_rule_fails_on_invalid(): void
    {
        $errors = Validator::validate(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_email_rule_passes_on_valid(): void
    {
        $errors = Validator::validate(['email' => 'test@example.com'], ['email' => 'email']);
        $this->assertEmpty($errors);
    }

    public function test_min_rule_fails_when_too_short(): void
    {
        $errors = Validator::validate(['pass' => 'abc'], ['pass' => 'min:6']);
        $this->assertArrayHasKey('pass', $errors);
    }

    public function test_min_rule_passes_when_long_enough(): void
    {
        $errors = Validator::validate(['pass' => 'abcdef'], ['pass' => 'min:6']);
        $this->assertEmpty($errors);
    }

    public function test_max_rule_fails_when_too_long(): void
    {
        $errors = Validator::validate(['name' => 'toolongname'], ['name' => 'max:5']);
        $this->assertArrayHasKey('name', $errors);
    }

    public function test_integer_rule_fails_on_non_integer(): void
    {
        $errors = Validator::validate(['age' => 'abc'], ['age' => 'integer']);
        $this->assertArrayHasKey('age', $errors);
    }

    public function test_integer_rule_passes_on_valid_integer(): void
    {
        $errors = Validator::validate(['age' => '25'], ['age' => 'integer']);
        $this->assertEmpty($errors);
    }

    public function test_nullable_skips_validation_on_null(): void
    {
        $errors = Validator::validate(['field' => null], ['field' => 'nullable|email']);
        $this->assertEmpty($errors);
    }

    public function test_nullable_still_validates_non_null_value(): void
    {
        $errors = Validator::validate(['field' => 'bad'], ['field' => 'nullable|email']);
        $this->assertArrayHasKey('field', $errors);
    }

    public function test_pipe_separates_multiple_rules(): void
    {
        $errors = Validator::validate(
            ['email' => 'bad'],
            ['email' => 'required|email']
        );
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_validate_or_fail_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        Validator::validateOrFail(['email' => 'not-valid'], ['email' => 'required|email']);
    }

    public function test_validate_or_fail_returns_data_on_success(): void
    {
        $data   = ['email' => 'user@example.com'];
        $result = Validator::validateOrFail($data, ['email' => 'required|email']);
        $this->assertSame($data, $result);
    }

    public function test_validation_exception_has_field_level_errors(): void
    {
        try {
            Validator::validateOrFail(['email' => ''], ['email' => 'required']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->getErrors());
        }
    }

    public function test_numeric_rule_passes_for_integer(): void
    {
        $errors = Validator::validate(['precio' => 150], ['precio' => 'numeric']);
        $this->assertEmpty($errors);
    }

    public function test_numeric_rule_passes_for_float(): void
    {
        $errors = Validator::validate(['precio' => '19.99'], ['precio' => 'numeric']);
        $this->assertEmpty($errors);
    }

    public function test_numeric_rule_fails_for_string(): void
    {
        $errors = Validator::validate(['precio' => 'abc'], ['precio' => 'numeric']);
        $this->assertArrayHasKey('precio', $errors);
    }

    public function test_confirmed_rule_passes_when_fields_match(): void
    {
        $errors = Validator::validate(
            ['clave' => 'secret', 'clave_confirmation' => 'secret'],
            ['clave' => 'confirmed']
        );
        $this->assertEmpty($errors);
    }

    public function test_confirmed_rule_fails_when_fields_differ(): void
    {
        $errors = Validator::validate(
            ['clave' => 'secret', 'clave_confirmation' => 'different'],
            ['clave' => 'confirmed']
        );
        $this->assertArrayHasKey('clave', $errors);
    }

    public function test_confirmed_rule_fails_when_confirmation_missing(): void
    {
        $errors = Validator::validate(
            ['clave' => 'secret'],
            ['clave' => 'confirmed']
        );
        $this->assertArrayHasKey('clave', $errors);
    }
}
