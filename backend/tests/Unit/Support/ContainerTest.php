<?php

namespace Tests\Unit\Support;

use App\Support\Container;
use App\Exceptions\NotFoundException;
use Tests\Unit\Support\Fixtures\ContainerMakeWithFixture;
use Tests\Unit\UnitTestCase;

class ContainerTest extends UnitTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_singleton_returns_same_instance(): void
    {
        $this->container->singleton('key', fn () => new \stdClass());

        $a = $this->container->get('key');
        $b = $this->container->get('key');

        $this->assertSame($a, $b);
    }

    public function test_bind_returns_new_instance_each_time(): void
    {
        $this->container->bind('key', fn () => new \stdClass());

        $a = $this->container->get('key');
        $b = $this->container->get('key');

        $this->assertNotSame($a, $b);
    }

    public function test_instance_stores_concrete_value(): void
    {
        $obj = new \stdClass();
        $obj->foo = 'bar';

        $this->container->instance('obj', $obj);

        $this->assertSame($obj, $this->container->get('obj'));
    }

    public function test_has_returns_true_for_registered_binding(): void
    {
        $this->container->bind('key', fn () => new \stdClass());
        $this->assertTrue($this->container->has('key'));
    }

    public function test_has_returns_false_for_unregistered_class(): void
    {
        // PSR-11: has() only reflects explicit registrations, not autowirable classes
        $this->assertFalse($this->container->has(\stdClass::class));
    }

    public function test_has_returns_false_for_unknown_key(): void
    {
        $this->assertFalse($this->container->has('UnknownClass\That\Does\Not\Exist'));
    }

    public function test_autowires_class_without_constructor(): void
    {
        $result = $this->container->get(\stdClass::class);
        $this->assertInstanceOf(\stdClass::class, $result);
    }

    public function test_throws_not_found_for_nonexistent_class(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->get('NonExistent\Class\Name');
    }

    public function test_make_with_injects_scalar_into_builtin_param(): void
    {
        $result = $this->container->makeWith(ContainerMakeWithFixture::class, 'hello');
        $this->assertSame('hello', $result->value);
    }
}
