<?php

declare(strict_types=1);

namespace Test;

use Phluxor\ActorSystem;
use Phluxor\Remote\Config;
use Phluxor\Remote\Kind;
use Phluxor\Remote\Remote;
use PHPUnit\Framework\TestCase;
use Test\ProtoBuf\HelloRequest;

use function Swoole\Coroutine\run;

class RemoteKindsTest extends TestCase
{
    public function testStartWithAdvertisedHost(): void
    {
        run(function () {
            $ids = [];
            \Swoole\Coroutine\go(function () use (&$ids) {
                $system = ActorSystem::create();
                $config = new Config(
                    'localhost',
                    50053,
                    Config::withKinds(
                        new Kind(
                            'someKind',
                            ActorSystem\Props::fromFunction(
                                new ActorSystem\Message\ReceiveFunction(
                                    function (ActorSystem\Context\ContextInterface $context) use (&$ids) {
                                        if ($context->message() instanceof HelloRequest) {
                                            $ids[] = $context->self()?->protobufPid()?->getId();
                                        }
                                    }
                                )
                            )
                        ),
                    ),
                    Config::withUseWebSocket(true)
                );
                $remote = new Remote($system, $config);
                $remote->start();
                \Swoole\Coroutine::sleep(3);
                $remote->shutdown();
                $this->assertCount(3, array_unique($ids));
            });
            \Swoole\Coroutine\go(function () {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50052, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                \Swoole\Coroutine::sleep(0.1);
                $rs = [];
                for ($i = 0; $i < 3; $i++) {
                    $r = $remote->spawnNamed('localhost:50053', (string) $i, 'someKind', 1);
                    $rs[] = $r;
                }
                foreach ($rs as $r) {
                    $system->root()->send(new ActorSystem\Ref($r->getPid()), new HelloRequest());
                }
                \Swoole\Coroutine::sleep(2);
                $remote->shutdown();
            });
        });
    }
}
