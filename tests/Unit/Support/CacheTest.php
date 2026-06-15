<?php

namespace Tests\Unit\Support;

use App\Support\Cache\ArrayCache;
use Tests\Unit\UnitTestCase;

class CacheTest extends UnitTestCase
{
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
    }

    public function test_set_and_get_value(): void
    {
        $this->cache->set('key', 'value');
        $this->assertSame('value', $this->cache->get('key'));
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->cache->get('missing'));
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        $this->cache->set('key', 42);
        $this->assertTrue($this->cache->has('key'));
    }

    public function test_has_returns_false_for_missing_key(): void
    {
        $this->assertFalse($this->cache->has('missing'));
    }

    public function test_delete_removes_key(): void
    {
        $this->cache->set('key', 'value');
        $this->cache->delete('key');
        $this->assertFalse($this->cache->has('key'));
        $this->assertNull($this->cache->get('key'));
    }

    public function test_stores_arbitrary_types(): void
    {
        $this->cache->set('int', 1);
        $this->cache->set('arr', ['a' => 1]);
        $this->cache->set('bool', true);

        $this->assertSame(1, $this->cache->get('int'));
        $this->assertSame(['a' => 1], $this->cache->get('arr'));
        $this->assertTrue($this->cache->get('bool'));
    }

    public function test_zero_ttl_never_expires(): void
    {
        $this->cache->set('key', 'value', 0);
        $this->assertSame('value', $this->cache->get('key'));
    }
}
