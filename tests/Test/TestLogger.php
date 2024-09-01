<?php

declare(strict_types=1);

namespace Test;

use Monolog\Logger;
use Phluxor\ActorSystem;
use Phluxor\ActorSystem\Logger\LoggerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class TestLogger implements LoggerInterface
{
    private ?Logger $logger = null;

    public function __invoke(ActorSystem $actorSystem): PsrLoggerInterface
    {
        $log = new \Monolog\Logger('Phluxor');
        $log->useLoggingLoopDetection(false);
        $log->pushHandler(new \Monolog\Handler\TestHandler());
        $this->logger = $log;
        return $log;
    }

    public function logger(): Logger
    {
        if ($this->logger === null) {
            throw new \RuntimeException('Logger not initialized');
        }
        return $this->logger;
    }
}
