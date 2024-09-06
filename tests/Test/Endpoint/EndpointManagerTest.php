<?php

declare(strict_types=1);

namespace Test\Endpoint;

use Phluxor\ActorSystem;
use Phluxor\Remote\ConcurrentMap;
use Phluxor\Remote\Config;
use Phluxor\Remote\Endpoint\EndpointManager;
use Phluxor\Remote\Remote;
use PHPUnit\Framework\TestCase;
use Test\TestLogger;

use function Swoole\Coroutine\run;

class EndpointManagerTest extends TestCase
{
    public function testShouldReturnEndpointWriterMailbox(): void
    {
        run(function () {
            \Swoole\Coroutine\go(function () {
                $actorSystemConfig = new ActorSystem\Config();
                $logger = new TestLogger();
                $system = ActorSystem::create($actorSystemConfig->setLoggerFactory($logger));
                $config = new Config('localhost', 50052);
                $remote = new Remote($system, $config);
                $edm = new EndpointManager(
                    $remote,
                    $config->isUseWebSocket()
                );
                $edm->start();
                $record = $logger->logger()->getHandlers()[0]->getRecords()[2];
                $this->assertSame('Started EndpointManager', $record['message']);
                $edm->stop();
                // should return ConcurrentMap
                $this->assertInstanceof(ConcurrentMap::class, $edm->getEndpointReaderConnections());
            });
        });
    }
}
