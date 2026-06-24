<?php

namespace Tests\Unit\Support;

use App\Support\Response;
use Tests\Unit\UnitTestCase;

class ResponseTest extends UnitTestCase
{
    public function test_success_returns_200_with_success_true(): void
    {
        $response = Response::success(['key' => 'value']);
        $this->assertSame(200, $response->getStatus());
    }

    public function test_success_with_custom_status(): void
    {
        $response = Response::success([], 201);
        $this->assertSame(201, $response->getStatus());
    }

    public function test_error_returns_422_by_default(): void
    {
        $response = Response::error('Something went wrong');
        $this->assertSame(422, $response->getStatus());
    }

    public function test_error_with_custom_status(): void
    {
        $response = Response::error('Not found', 404);
        $this->assertSame(404, $response->getStatus());
    }

    public function test_redirect_returns_302_by_default(): void
    {
        $response = Response::redirect('/dashboard');
        $this->assertSame(302, $response->getStatus());
    }

    public function test_redirect_with_custom_status(): void
    {
        $response = Response::redirect('/login', 301);
        $this->assertSame(301, $response->getStatus());
    }

    public function test_with_status_creates_new_instance(): void
    {
        $original = Response::success([]);
        $modified = $original->withStatus(201);

        $this->assertNotSame($original, $modified);
        $this->assertSame(200, $original->getStatus());
        $this->assertSame(201, $modified->getStatus());
    }

    public function test_send_outputs_json(): void
    {
        $response = Response::success(['name' => 'John']);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertTrue($decoded['success']);
        $this->assertSame('John', $decoded['data']['name']);
    }

    public function test_get_headers_returns_default_content_type(): void
    {
        $response = Response::success([]);
        $headers  = $response->getHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertStringContainsString('application/json', $headers['Content-Type']);
    }

    public function test_with_header_visible_via_get_headers(): void
    {
        $response = Response::success([])
            ->withHeader('X-Custom', 'my-value');

        $this->assertSame('my-value', $response->getHeaders()['X-Custom']);
    }

    public function test_with_header_does_not_mutate_original(): void
    {
        $original = Response::success([]);
        $modified = $original->withHeader('X-Foo', 'bar');

        $this->assertArrayNotHasKey('X-Foo', $original->getHeaders());
        $this->assertArrayHasKey('X-Foo', $modified->getHeaders());
    }
}
