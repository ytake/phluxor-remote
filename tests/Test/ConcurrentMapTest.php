<?php

declare(strict_types=1);

namespace Test;

use Phluxor\Remote\ConcurrentMap;
use PHPUnit\Framework\TestCase;

class ConcurrentMapTest extends TestCase
{
    private ConcurrentMap $map;

    protected function setUp(): void
    {
        $this->map = new ConcurrentMap();
    }

    public function testSetAndGet(): void
    {
        $this->map->set('key1', 'value1');
        $this->assertSame('value1', $this->map->get('key1'));
    }

    public function testGetNonExistentKey(): void
    {
        $this->assertNull($this->map->get('nonexistent'));
    }

    public function testDelete(): void
    {
        $this->map->set('key1', 'value1');
        $this->map->delete('key1');
        $this->assertNull($this->map->get('key1'));
    }

    public function testHas(): void
    {
        $this->map->set('key1', 'value1');
        $this->assertTrue($this->map->has('key1'));
        $this->assertFalse($this->map->has('key2'));
    }

    public function testClear(): void
    {
        $this->map->set('key1', 'value1');
        $this->map->set('key2', 'value2');
        $this->map->clear();
        $this->assertNull($this->map->get('key1'));
        $this->assertNull($this->map->get('key2'));
    }

    public function testGetOrSet(): void
    {
        $result = $this->map->getOrSet('key1', 'value1');
        $this->assertSame('value1', $result->actual);
        $this->assertFalse($result->loaded);

        $result = $this->map->getOrSet('key1', 'value2');
        $this->assertSame('value1', $result->actual);
        $this->assertTrue($result->loaded);
    }

    public function testRange(): void
    {
        $this->map->set('key1', 'value1');
        $this->map->set('key2', 'value2');

        $result = [];
        $this->map->range(function ($key, $value) use (&$result) {
            $result[$key] = $value;
            return true;
        });

        $this->assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
        ], $result);
    }

    public function testRangeStopsEarly(): void
    {
        $this->map->set('key1', 'value1');
        $this->map->set('key2', 'value2');
        $this->map->set('key3', 'value3');

        $result = [];
        $this->map->range(function ($key, $value) use (&$result) {
            $result[$key] = $value;
            return $key !== 'key2';
        });
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('key1', $result);
        $this->assertArrayHasKey('key2', $result);
        $this->assertArrayNotHasKey('key3', $result);
    }
}
