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
        private string $address,
        private Config $config,
        private SerializerManager $serializerManager
    ) {
    }

    public function __invoke(): ActorInterface
    {
        [$host, $port] = explode(':', $this->address);
        return new EndpointWriter(
            $this->config,
            $host,
            (int) $port,
            $this->config->isSsl(),
            $this->remote,
            $this->serializerManager
        );
    }
}