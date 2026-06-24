<?php

namespace Tests\Unit\Support;

use App\Support\Validator;
use Tests\Unit\UnitTestCase;

class ValidatorExtendedTest extends UnitTestCase
{
    // ── string ────────────────────────────────────────────────────────────────

    public function test_string_passes_for_string_value(): void
    {
        $errors = Validator::validate(['name' => 'Alice'], ['name' => 'string']);
        $this->assertEmpty($errors);
    }

    public function test_string_fails_for_integer_value(): void
    {
        $errors = Validator::validate(['name' => 42], ['name' => 'string']);
        $this->assertArrayHasKey('name', $errors);
    }

    public function test_string_passes_for_null_when_nullable(): void
    {
        $errors = Validator::validate(['name' => null], ['name' => 'nullable|string']);
        $this->assertEmpty($errors);
    }

    // ── array ─────────────────────────────────────────────────────────────────

    public function test_array_passes_for_array_value(): void
    {
        $errors = Validator::validate(['items' => [1, 2]], ['items' => 'array']);
        $this->assertEmpty($errors);
    }

    public function test_array_fails_for_string_value(): void
    {
        $errors = Validator::validate(['items' => 'not-an-array'], ['items' => 'array']);
        $this->assertArrayHasKey('items', $errors);
    }

    // ── url ───────────────────────────────────────────────────────────────────

    public function test_url_passes_for_valid_url(): void
    {
        $errors = Validator::validate(['link' => 'https://example.com'], ['link' => 'url']);
        $this->assertEmpty($errors);
    }

    public function test_url_fails_for_invalid_url(): void
    {
        $errors = Validator::validate(['link' => 'not a url'], ['link' => 'url']);
        $this->assertArrayHasKey('link', $errors);
    }

    // ── date ──────────────────────────────────────────────────────────────────

    public function test_date_passes_for_valid_date(): void
    {
        $errors = Validator::validate(['dob' => '1990-05-20'], ['dob' => 'date']);
        $this->assertEmpty($errors);
    }

    public function test_date_fails_for_invalid_date(): void
    {
        $errors = Validator::validate(['dob' => '32-13-2000'], ['dob' => 'date']);
        $this->assertArrayHasKey('dob', $errors);
    }

    public function test_date_with_custom_format(): void
    {
        $errors = Validator::validate(['dob' => '20/05/1990'], ['dob' => 'date:d/m/Y']);
        $this->assertEmpty($errors);
    }

    // ── regex ─────────────────────────────────────────────────────────────────

    public function test_regex_passes_for_matching_value(): void
    {
        $errors = Validator::validate(['code' => 'ABC-123'], ['code' => 'regex:/^[A-Z]{3}-\d{3}$/']);
        $this->assertEmpty($errors);
    }

    public function test_regex_fails_for_non_matching_value(): void
    {
        $errors = Validator::validate(['code' => 'abc-123'], ['code' => 'regex:/^[A-Z]{3}-\d{3}$/']);
        $this->assertArrayHasKey('code', $errors);
    }

    // ── uuid ──────────────────────────────────────────────────────────────────

    public function test_uuid_passes_for_valid_uuid(): void
    {
        $errors = Validator::validate(
            ['id' => '550e8400-e29b-41d4-a716-446655440000'],
            ['id' => 'uuid']
        );
        $this->assertEmpty($errors);
    }

    public function test_uuid_fails_for_invalid_uuid(): void
    {
        $errors = Validator::validate(['id' => 'not-a-uuid'], ['id' => 'uuid']);
        $this->assertArrayHasKey('id', $errors);
    }

    public function test_uuid_passes_for_null_when_nullable(): void
    {
        $errors = Validator::validate(['id' => null], ['id' => 'nullable|uuid']);
        $this->assertEmpty($errors);
    }
}
