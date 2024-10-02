<?php

declare(strict_types=1);

namespace Test;

use Phluxor\ActorSystem\ProtoBuf\Pid;
use Phluxor\ActorSystem\Ref;
use Phluxor\Remote\Endpoint;
use PHPUnit\Framework\TestCase;

class EndpointTest extends TestCase
{
    public function testShouldReturnWatcherAddress(): void
    {
        $endpoint = new Endpoint(
            writer: new Ref(new Pid(['address' => 'node1', 'id' => '1'])),
            watcher: new Ref(new Pid(['address' => 'localhost', 'id' => '2']))
        );
        $this->assertSame('localhost', $endpoint->address());
    }

    public function testShouldReturnEmptyAddress(): void
    {
        $endpoint = new Endpoint(
            writer: new Ref(new Pid(['address' => 'node1', 'id' => '1'])),
            watcher: null
        );
        $this->assertSame('', $endpoint->address());
    }
}
