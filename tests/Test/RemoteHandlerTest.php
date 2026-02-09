<?php

declare(strict_types=1);

namespace Test;

use Phluxor\ActorSystem;
use Phluxor\Remote\Config;
use Phluxor\Remote\Remote;
use Phluxor\Remote\RemoteHandler;
use Phluxor\Remote\RemoteProcess;
use PHPUnit\Framework\TestCase;

use function Swoole\Coroutine\run;

class RemoteHandlerTest extends TestCase
{
    public function testShouldThrowRuntimeException(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $actorSystemConfig = new ActorSystem\Config();
                $logger = new TestLogger();
                $system = ActorSystem::create(
                    $actorSystemConfig->setLoggerFactory($logger)
                );
                $config = new Config('localhost', 50052);
                $remote = new Remote($system, $config);
                $process = false;
                try {
                    $handler = new RemoteHandler($remote);
                    $handler(null);
                } catch (\RuntimeException $e) {
                    $this->assertSame('Cannot resolve null pid', $e->getMessage());
                    $process = true;
                }
                $this->assertTrue($process);
            });
        });
    }

    public function testShouldReturnProcessRegistryResult(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $actorSystemConfig = new ActorSystem\Config();
                $logger = new TestLogger();
                $system = ActorSystem::create(
                    $actorSystemConfig->setLoggerFactory($logger)
                );
                $config = new Config('localhost', 50052);
                $remote = new Remote($system, $config);
                $handler = new RemoteHandler($remote);
                $ref = $system->root()->spawn(
                    ActorSystem\Props::fromProducer(
                        fn() => new VoidActor()
                    )
                );
                $result = $handler($ref);
                $this->assertTrue($result->isProcess());
                $this->assertInstanceOf(RemoteProcess::class, $result->getProcess());
            });
        });
    }
}
