<?php

namespace Tests\Unit\Support;

use App\Support\FormRequest;
use App\Exceptions\ValidationException;
use Tests\Unit\UnitTestCase;

class FormRequestTest extends UnitTestCase
{
    private function makeFormRequest(array $rules, array $data): FormRequest
    {
        return new class ($data, $rules) extends FormRequest {
            public function __construct(array $data, private array $ruleSet)
            {
                parent::__construct($data);
            }

            protected function rules(): array
            {
                return $this->ruleSet;
            }
        };
    }

    public function test_validated_returns_only_declared_fields(): void
    {
        $request = $this->makeFormRequest(
            ['nombre' => 'required', 'email' => 'required|email'],
            ['nombre' => 'Juan', 'email' => 'juan@test.com', 'admin' => true, 'extra' => 'ignored']
        );

        $validated = $request->validated();

        $this->assertSame(['nombre' => 'Juan', 'email' => 'juan@test.com'], $validated);
        $this->assertArrayNotHasKey('admin', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
    }

    public function test_all_returns_everything(): void
    {
        $data    = ['nombre' => 'Juan', 'extra' => 'included'];
        $request = $this->makeFormRequest(['nombre' => 'required'], $data);

        $this->assertSame($data, $request->all());
    }

    public function test_input_returns_value_by_key(): void
    {
        $request = $this->makeFormRequest(['nombre' => 'required'], ['nombre' => 'Ana']);
        $this->assertSame('Ana', $request->input('nombre'));
    }

    public function test_input_returns_default_when_missing(): void
    {
        $request = $this->makeFormRequest(['nombre' => 'required'], ['nombre' => 'Ana']);
        $this->assertSame('default', $request->input('missing', 'default'));
    }

    public function test_throws_when_required_field_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->makeFormRequest(['nombre' => 'required'], []);
    }

    public function test_validated_with_empty_rules_returns_empty(): void
    {
        $request   = $this->makeFormRequest([], ['nombre' => 'Juan', 'extra' => 'ignored']);
        $this->assertSame([], $request->validated());
    }

    public function test_group_routes_apply_shared_middleware(): void
    {
        // Router group test via FormRequest is separate — this ensures
        // validated() works when rules are a subset of data
        $request = $this->makeFormRequest(
            ['a' => 'required', 'b' => 'required'],
            ['a' => '1', 'b' => '2', 'c' => '3']
        );

        $this->assertCount(2, $request->validated());
    }
}
