<?php

namespace Tests\Unit\Support;

use App\Support\Router;
use App\Support\Container;
use App\Support\Request;
use App\Support\Response;
use App\Support\Pipeline;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AdminMiddleware;
use Tests\Unit\UnitTestCase;

class RouterGroupTest extends UnitTestCase
{
    private Router $router;
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->instance(self::class, $this);
        $this->router = new Router($this->container);
    }

    public function test_group_applies_middleware_to_all_routes(): void
    {
        $this->router->group([AuthMiddleware::class], function ($router) {
            $router->get('/a', [self::class, 'pingAction']);
            $router->get('/b', [self::class, 'pingAction']);
        });

        $routes = $this->router->getRegisteredRoutes();

        $this->assertCount(2, $routes);
        $this->assertContains(AuthMiddleware::class, $routes[0]['middlewares']);
        $this->assertContains(AuthMiddleware::class, $routes[1]['middlewares']);
    }

    public function test_nested_groups_merge_middlewares(): void
    {
        $this->router->group([AuthMiddleware::class], function ($router) {
            $router->group([AdminMiddleware::class], function ($router) {
                $router->get('/admin', [self::class, 'pingAction']);
            });
        });

        $routes = $this->router->getRegisteredRoutes();

        $this->assertContains(AuthMiddleware::class, $routes[0]['middlewares']);
        $this->assertContains(AdminMiddleware::class, $routes[0]['middlewares']);
    }

    public function test_routes_outside_group_have_no_middleware(): void
    {
        $this->router->get('/public', [self::class, 'pingAction']);

        $this->router->group([AuthMiddleware::class], function ($router) {
            $router->get('/private', [self::class, 'pingAction']);
        });

        $routes = $this->router->getRegisteredRoutes();

        $this->assertEmpty($routes[0]['middlewares']);
        $this->assertNotEmpty($routes[1]['middlewares']);
    }

    public function test_group_does_not_leak_middleware_after_callback(): void
    {
        $this->router->group([AuthMiddleware::class], function ($router) {
            $router->get('/inside', [self::class, 'pingAction']);
        });

        $this->router->get('/outside', [self::class, 'pingAction']);

        $routes = $this->router->getRegisteredRoutes();

        $this->assertEmpty($routes[1]['middlewares']);
    }

    public function pingAction(Request $request): Response
    {
        return Response::success([]);
    }

    public function test_group_with_prefix_routes_correctly(): void
    {
        $this->router->group([], '/v1', function ($router) {
            $router->get('/ping', [self::class, 'pingAction']);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/v1/ping';
        $response = $this->router->dispatch(new Request(), new \App\Support\Pipeline());

        $this->assertSame(200, $response->getStatus());
    }

    public function test_group_prefix_does_not_match_without_prefix(): void
    {
        $this->router->group([], '/api', function ($router) {
            $router->get('/ping', [self::class, 'pingAction']);
        });

        $this->expectException(\App\Exceptions\NotFoundException::class);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/ping';
        $this->router->dispatch(new Request(), new \App\Support\Pipeline());
    }

    public function test_nested_group_prefixes_are_combined(): void
    {
        $this->router->group([], '/v1', function ($router) {
            $router->group([], '/admin', function ($router) {
                $router->get('/ping', [self::class, 'pingAction']);
            });
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/v1/admin/ping';
        $response = $this->router->dispatch(new Request(), new \App\Support\Pipeline());

        $this->assertSame(200, $response->getStatus());
    }

    public function test_registered_routes_include_prefix_in_uri(): void
    {
        $this->router->group([], '/v2', function ($router) {
            $router->get('/items', [self::class, 'pingAction']);
        });

        $routes = $this->router->getRegisteredRoutes();
        $this->assertSame('/v2/items', $routes[0]['uri']);
    }
}
