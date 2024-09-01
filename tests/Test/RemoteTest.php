<?php

declare(strict_types=1);

namespace Test;

use Monolog\Test\TestCase;
use Phluxor\ActorSystem;
use Phluxor\Remote\Config;
use Phluxor\Remote\Endpoint\WebSocket\EndpointWriter;
use Phluxor\Remote\Kind;
use Phluxor\Remote\Message\EndpointConnectedEvent;
use Phluxor\Remote\ProtoBuf\MessageBatch;
use Phluxor\Remote\Remote;

use function Swoole\Coroutine\run;

class RemoteTest extends TestCase
{
    public function testStartWithAdvertisedHost(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50052, Config::withAdvertisedHost('Hi'));
                $remote = new Remote($system, $config);
                $remote->start();
                \Swoole\Coroutine::sleep(0.1);
                $this->assertSame('Hi', $system->address());
                $remote->shutdown(true);
            });
        });
    }

    public function testRemoteRegister(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $system = ActorSystem::create();
                $config = new Config(
                    'localhost',
                    0,
                    Config::withKinds(
                        new Kind(
                            'someKind',
                            ActorSystem\Props::fromProducer(
                                fn() => new VoidActor()
                            )
                        ),
                        new Kind(
                            'someOther',
                            ActorSystem\Props::fromProducer(
                                fn() => new VoidActor()
                            )
                        ),
                    ),
                    Config::withUseWebSocket(true),
                );
                $remote = new Remote($system, $config);
                $kinds = $remote->getKnownKinds();
                $this->assertCount(2, $kinds);
                $this->assertSame('someKind', $kinds[0]);
                $this->assertSame('someOther', $kinds[1]);
            });
        });
    }

    public function testStartRemoteConnection(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50052, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                \Swoole\Coroutine::sleep(0.1);
                $ref = $system->root()->spawn(
                    ActorSystem\Props::fromProducer(
                        fn() => new EndpointWriter(
                            $config,
                            'localhost',
                            50052,
                            false,
                            $remote,
                            $remote->getSerializerManager()
                        )
                    )
                );
                $isProcess = false;
                $system->getEventStream()?->subscribe(function ($msg) use (&$isProcess) {
                    if ($msg instanceof EndpointConnectedEvent) {
                        $isProcess = true;
                    }
                });
                \Swoole\Coroutine::sleep(1);
                $remote->shutdown(false);
                $system->root()->stop($ref);
                $this->assertTrue($isProcess);
            });
        });
    }
}
