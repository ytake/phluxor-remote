<?php

declare(strict_types=1);

namespace Test;

use Phluxor\Remote\ActivatorError;
use PHPUnit\Framework\TestCase;

class ActivatorErrorTest extends TestCase
{
    public function testShouldReturnError(): void
    {
        $error = new ActivatorError(1, true);
        $this->assertSame(1, $error->code);
        $this->assertTrue($error->error);
        $this->assertSame('Error code: 1', $error->error());
    }

    public function testShouldReturnNoError(): void
    {
        $error = new ActivatorError(1, true);
        $this->assertSame(1, $error->code);
        $this->assertTrue($error->error);
        $this->assertSame('Error code: 1', $error->error());
    }
}
