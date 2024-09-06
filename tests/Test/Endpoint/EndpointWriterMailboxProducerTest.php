<?php

declare(strict_types=1);

namespace Test\Endpoint;

use Phluxor\Remote\Endpoint\EndpointWriterMailbox;
use Phluxor\Remote\Endpoint\EndpointWriterMailboxProducer;
use PHPUnit\Framework\TestCase;

class EndpointWriterMailboxProducerTest extends TestCase
{
    public function testShouldReturnEndpointWriterMailbox(): void
    {
        $producer = new EndpointWriterMailboxProducer(1, 1);
        $this->assertInstanceOf(EndpointWriterMailbox::class, $producer());
    }
}
