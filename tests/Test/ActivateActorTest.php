<?php

declare(strict_types=1);

namespace Test;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Phluxor\ActorSystem;
use Phluxor\Remote\ActivateActor;
use Phluxor\Remote\Config;
use Phluxor\Remote\Message\Ping;
use Phluxor\Remote\Message\Pong;
use Phluxor\Remote\ProtoBuf\ActorPidRequest;
use Phluxor\Remote\ProtoBuf\ActorPidResponse;
use Phluxor\Remote\Remote;
use PHPUnit\Framework\TestCase;

use function Swoole\Coroutine\run;

class ActivateActorTest extends TestCase
{
    public function testShouldStartActivator(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $actorSystemConfig = new ActorSystem\Config();
                $logger = new TestLogger();
                $system = ActorSystem::create($actorSystemConfig->setLoggerFactory($logger));
                $config = new Config('localhost', 50052);
                $remote = new Remote($system, $config);
                $ref = $system->root()->spawn(
                    ActorSystem\Props::fromProducer(
                        fn() => new ActivateActor($remote)
                    )
                );
                $this->assertNotNull($ref);
                $future = $system->root()->requestFuture($ref, new Ping(), 1);
                $v = $future->result()->value();
                $this->assertInstanceOf(Pong::class, $v);
                $records = $logger->logger()->getHandlers()[0]->getRecords();
                $this->assertCount(2, $records);
                $this->assertSame('Started Activator', $records[1]['message']);
            });
        });
    }

    public function testShouldReturnActorPidResponse(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $actorSystemConfig = new ActorSystem\Config();
                $logger = new TestLogger();
                $system = ActorSystem::create($actorSystemConfig->setLoggerFactory($logger));
                $config = new Config('localhost', 50052);
                $remote = new Remote($system, $config);
                $ref = $system->root()->spawn(
                    ActorSystem\Props::fromProducer(
                        fn() => new ActivateActor($remote)
                    )
                );
                $this->assertNotNull($ref);
                $future = $system->root()->requestFuture($ref, new ActorPidRequest(), 1);
                /** @var ActorPidResponse $v */
                $v = $future->result()->value();
                $this->assertInstanceOf(ActorPidResponse::class, $v);
                $this->assertSame(4, $v->getStatusCode());
            });
        });
    }
}
