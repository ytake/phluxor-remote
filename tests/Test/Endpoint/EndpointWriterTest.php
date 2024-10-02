<?php

declare(strict_types=1);

namespace Test\Endpoint;

use Phluxor\ActorSystem;
use Phluxor\ActorSystem\ProtoBuf\Pid;
use Phluxor\Remote\Config;
use Phluxor\Remote\Endpoint\WebSocket\EndpointWriter;
use Phluxor\Remote\Remote;
use Phluxor\Remote\Serializer\SerializerManager;
use Phluxor\Remote\WebSocket\ProtoBuf\RemotingClient;
use Phluxor\WebSocket\Client;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function Swoole\Coroutine\run;

class EndpointWriterTest extends TestCase
{
    private function createEndpointWriter(): EndpointWriter
    {
        $system = ActorSystem::create();
        $c = new Config('localhost', 50052);
        return new EndpointWriter(
            $c,
            new RemotingClient(
                new Client($c->host, $c->port)
            ),
            $c->host,
            new Remote($system, $c),
            new SerializerManager()
        );
    }

    public function testShouldReturnAddress(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $ew = $this->createEndpointWriter();
                $rc = new ReflectionClass($ew);
                $method = $rc->getMethod('address');
                $this->assertSame('localhost', $method->invoke($ew));
            });
        });
    }

    public function testAddToTargetLookup(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $ew = $this->createEndpointWriter();
                $m = [];
                $names = [];
                $rc = new ReflectionClass($ew);
                $method = $rc->getMethod('addToTargetLookup');
                for ($i = 0; $i < 10; $i++) {
                    $l = $method->invokeArgs($ew, [
                        &$m,
                        new Pid(['address' => 'localhost:50052', 'id' => 'test']),
                        $names,
                    ]);
                    $names = $l['lookup'];
                }
                $this->assertCount(1, $names);
                /** @var Pid $name */
                foreach ($names as $name) {
                    $this->assertSame('test', $name->getId());
                }
            });
        });
    }

    public function testAddToSenderLookup(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $ew = $this->createEndpointWriter();
                $m = [];
                $names = [];
                $rc = new ReflectionClass($ew);
                $method = $rc->getMethod('addToSenderLookup');
                for ($i = 0; $i < 10; $i++) {
                    $l = $method->invokeArgs($ew, [
                        &$m,
                        new Pid(['address' => 'localhost:50052', 'id' => 'test']),
                        $names,
                    ]);
                    $names = $l['lookup'];
                }
                $this->assertCount(1, $names);
                /** @var Pid $name */
                foreach ($names as $name) {
                    $this->assertSame('test', $name->getId());
                }
            });
        });
    }
}
