<?php

declare(strict_types=1);

namespace Test;

use Phluxor\ActorSystem;
use Phluxor\Remote\Config;
use Phluxor\Remote\Kind;
use Phluxor\Remote\Remote;
use PHPUnit\Framework\TestCase;
use Test\ProtoBuf\HelloRequest;
use Test\ProtoBuf\HelloResponse;

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
                $config = new Config('localhost', 50053, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                $props = ActorSystem\Props::fromFunction(
                    new ActorSystem\Message\ReceiveFunction(
                        function (ActorSystem\Context\ContextInterface $context) {
                            $message = $context->message();
                            if ($message instanceof HelloRequest) {
                                $context->respond(new HelloResponse([
                                    'Message' => 'Hello from remote node',
                                ]));
                            }
                        }
                    )
                );
                $system->root()->spawnNamed($props, 'hello');
                \Swoole\Coroutine::sleep(2);
                $remote->shutdown();
            });
            \Swoole\Coroutine\go(function () {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50052, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                \Swoole\Coroutine::sleep(0.1);
                $future = $system->root()->requestFuture(
                    new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                        'address' => 'localhost:50053',
                        'id' => 'hello',
                    ])),
                    new HelloRequest(),
                    1
                );
                $r = $future->result()->value();
                $this->assertInstanceOf(HelloResponse::class, $r);
                $this->assertSame('Hello from remote node', $r->getMessage());

                $future = $system->root()->requestFuture(
                    new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                        'address' => 'localhost:50053',
                        'id' => 'hello',
                    ])),
                    new HelloRequest(),
                    1
                );
                $r = $future->result()->value();
                $this->assertInstanceOf(HelloResponse::class, $r);
                $remote->shutdown();
            });
        });
    }
}
