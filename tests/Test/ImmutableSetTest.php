<?php

declare(strict_types=1);

namespace Test;

use Phluxor\Remote\ImmutableSet;
use PHPUnit\Framework\TestCase;

class ImmutableSetTest extends TestCase
{
    public function testImmutableSet(): void
    {
        $is = new ImmutableSet([]);
        $this->assertInstanceOf(ImmutableSet::class, $is);
        $this->assertEquals(0, $is->size());
        $this->assertFalse($is->contains('test'));
        $is = $is->add('test');
        $this->assertEquals(1, $is->size());
        $this->assertTrue($is->contains('test'));
        $is = $is->add('test2');
        $this->assertEquals(2, $is->size());
        $this->assertTrue($is->contains('test2'));
        $is = $is->addRange(['test3', 'test4']);
        $this->assertEquals(4, $is->size());
        $this->assertTrue($is->contains('test3'));
        $this->assertTrue($is->contains('test4'));
    }
}
