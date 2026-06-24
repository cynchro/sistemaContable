<?php

namespace Tests\Unit\Support;

use App\Support\Request;
use Tests\Unit\UnitTestCase;

class RequestTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/test',
        ];
        $_POST  = [];
        $_GET   = [];
        $_FILES = [];
    }

    public function test_method_returns_uppercase(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';
        $request = new Request();
        $this->assertSame('POST', $request->method());
    }

    public function test_uri_returns_path_only(): void
    {
        $_SERVER['REQUEST_URI'] = '/users/42?foo=bar';
        $request = new Request();
        $this->assertSame('/users/42', $request->uri());
    }

    public function test_header_reads_from_server(): void
    {
        $_SERVER['HTTP_X_CUSTOM'] = 'value123';
        $request = new Request();
        $this->assertSame('value123', $request->header('X-Custom'));
    }

    public function test_bearer_token_extracts_from_authorization(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer my.jwt.token';
        $request = new Request();
        $this->assertSame('my.jwt.token', $request->bearerToken());
    }

    public function test_bearer_token_returns_null_when_missing(): void
    {
        $request = new Request();
        $this->assertNull($request->bearerToken());
    }

    public function test_set_and_get_user(): void
    {
        $request = new Request();
        $request->setUser(['sub' => 1, 'rol' => 1]);
        $this->assertSame(['sub' => 1, 'rol' => 1], $request->user());
    }

    public function test_user_is_null_by_default(): void
    {
        $request = new Request();
        $this->assertNull($request->user());
    }

    public function test_set_and_get_tenant_id(): void
    {
        $request = new Request();
        $request->setTenantId('tenant-uuid-abc');
        $this->assertSame('tenant-uuid-abc', $request->tenantId());
    }

    public function test_tenant_id_is_null_by_default(): void
    {
        $request = new Request();
        $this->assertNull($request->tenantId());
    }

    public function test_route_params_accessible_via_route(): void
    {
        $request = new Request();
        $request->setRouteParams(['id' => '99']);
        $this->assertSame('99', $request->route('id'));
    }

    public function test_route_returns_default_when_param_missing(): void
    {
        $request = new Request();
        $this->assertNull($request->route('missing'));
        $this->assertSame('default', $request->route('missing', 'default'));
    }

    public function test_input_from_post(): void
    {
        $_POST = ['name' => 'Alice'];
        $request = new Request();
        $this->assertSame('Alice', $request->input('name'));
    }

    public function test_all_merges_sources(): void
    {
        $_GET  = ['page' => '2'];
        $_POST = ['name' => 'Bob'];
        $request = new Request();
        $all = $request->all();
        $this->assertSame('2', $all['page']);
        $this->assertSame('Bob', $all['name']);
    }

    public function test_only_returns_specified_keys(): void
    {
        $_POST   = ['name' => 'Alice', 'email' => 'alice@test.com', 'admin' => true];
        $request = new Request();
        $result  = $request->only(['name', 'email']);

        $this->assertSame(['name' => 'Alice', 'email' => 'alice@test.com'], $result);
        $this->assertArrayNotHasKey('admin', $result);
    }

    public function test_except_excludes_specified_keys(): void
    {
        $_POST   = ['name' => 'Alice', 'password' => 'secret', 'token' => 'xyz'];
        $request = new Request();
        $result  = $request->except(['password', 'token']);

        $this->assertSame(['name' => 'Alice'], $result);
        $this->assertArrayNotHasKey('password', $result);
    }
}
