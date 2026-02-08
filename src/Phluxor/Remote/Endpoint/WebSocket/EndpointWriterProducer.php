<?php

declare(strict_types=1);

namespace Phluxor\Remote\Endpoint\WebSocket;

use Phluxor\ActorSystem\Message\ActorInterface;
use Phluxor\ActorSystem\Message\ProducerInterface;
use Phluxor\Remote\Config;
use Phluxor\Remote\Remote;
use Phluxor\Remote\Serializer\SerializerManager;
use Phluxor\Remote\WebSocket\ProtoBuf\RemotingClient;
use Phluxor\WebSocket\Client;

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
        $client = new RemotingClient(
            (new Client($host, (int) $port, $this->remote->config->isSsl()))->connect()
        );
        return new EndpointWriter(
            $this->config,
            $client,
            $this->address,
            $this->remote,
            $this->serializerManager
        );
    }
}
