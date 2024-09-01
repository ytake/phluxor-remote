<?php

declare(strict_types=1);

namespace Phluxor\Remote\Endpoint\WebSocket;

use Phluxor\ActorSystem\Message\ActorInterface;
use Phluxor\ActorSystem\Message\ProducerInterface;
use Phluxor\Remote\Config;
use Phluxor\Remote\Remote;
use Phluxor\Remote\Serializer\SerializerManager;

readonly class EndpointWriterProducer implements ProducerInterface
{
    public function __construct(
        private Remote $remote,
        private Config $config,
        private SerializerManager $serializerManager
    ) {
    }

    public function __invoke(): ActorInterface
    {
        return new EndpointWriter(
            $this->config,
            $this->config->host,
            $this->config->port,
            $this->config->isSsl(),
            $this->remote,
            $this->serializerManager
        );
    }
}
