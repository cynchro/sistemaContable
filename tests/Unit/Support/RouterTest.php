<?php

namespace Tests\Unit\Support;

use App\Support\Router;
use App\Support\Container;
use App\Support\Request;
use App\Support\Response;
use App\Support\Pipeline;
use App\Exceptions\NotFoundException;
use Tests\Unit\UnitTestCase;

class RouterTest extends UnitTestCase
{
    private Router $router;
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->instance(self::class, $this); // router uses $this as controller
        $this->router    = new Router($this->container);
    }

    private function buildRequest(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        return new Request();
    }

    private function pipeline(): Pipeline
    {
        return new Pipeline();
    }

    public function test_dispatches_get_route(): void
    {
        $this->router->get('/ping', [self::class, 'pingAction']);
        $request  = $this->buildRequest('GET', '/ping');
        $response = $this->router->dispatch($request, $this->pipeline());
        $this->assertSame(200, $response->getStatus());
    }

    public function test_throws_not_found_for_unknown_route(): void
    {
        $this->expectException(NotFoundException::class);
        $request = $this->buildRequest('GET', '/unknown-route-xyz');
        $this->router->dispatch($request, $this->pipeline());
    }

    public function test_extracts_route_params(): void
    {
        $this->router->get('/users/{id}', [self::class, 'echoIdAction']);
        $request  = $this->buildRequest('GET', '/users/42');
        $response = $this->router->dispatch($request, $this->pipeline());

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertSame('42', $data['data']['id']);
    }

    public function test_backward_compat_boolean_protected_true(): void
    {
        // true maps to [AuthMiddleware] — route should register without throwing
        $this->router->get('/protected', [self::class, 'pingAction'], true);
        $this->assertTrue(true); // just verify no exception on registration
    }

    // Stub actions for testing
    public function pingAction(Request $request): Response
    {
        return Response::success(['pong' => true]);
    }

    public function echoIdAction(Request $request): Response
    {
        return Response::success(['id' => $request->route('id')]);
    }
}
