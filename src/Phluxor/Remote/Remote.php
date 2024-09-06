<?php

/**
 * Copyright 2024 Yuuki Takezawa <yuuki.takezawa@comnect.jp.net>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Phluxor\Remote;

use Phluxor\ActorSystem;
use Phluxor\ActorSystem\Props;
use Phluxor\Remote\Endpoint\EndpointManager;
use Phluxor\Remote\Exception\AddressAlreadyException;
use Phluxor\Remote\Message\RemoteDeliver;
use Phluxor\Remote\ProtoBuf\ActorPidRequest;
use Phluxor\Remote\ProtoBuf\ActorPidResponse;
use Phluxor\Remote\Serializer\SerializerManager;
use Phluxor\Remote\WebSocket\ProtoBuf\RemotingService;
use Phluxor\Remote\WebSocket\ServiceContainer;
use Phluxor\Value\ContextExtensionId;
use Phluxor\Value\ExtensionInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Swoole\Coroutine\Channel;

class Remote implements ExtensionInterface
{
    private ContextExtensionId $extensionId;
    private BlockList $blockList;
    private ?EndpointManager $endpointManager = null;
    private ?RemotingService $endpointReader = null;
    private ?WebSocketServer $server = null;
    private SerializerManager $serializerManager;

    /** @var array<string, Props> */
    private array $kinds = [];

    public function __construct(
        public readonly ActorSystem $actorSystem,
        public readonly Config $config,
    ) {
        $this->extensionId = new ContextExtensionId();
        $this->blockList = new BlockList();
        foreach ($config->getKinds() as $key => $kind) {
            $this->kinds[$key] = $kind;
        }
        $this->actorSystem->extensions()->set($this);
        $this->serializerManager = new SerializerManager();
    }

    public function extensionID(): ContextExtensionId
    {
        return $this->extensionId;
    }

    /**
     * @param ActorSystem $actorSystem
     * @return Remote|null
     */
    public function getRemote(ActorSystem $actorSystem): ?Remote
    {
        $r = $actorSystem->extensions()->get($this->extensionID());
        return $r instanceof Remote ? $r : null;
    }

    public function getBlockList(): BlockList
    {
        return $this->blockList;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function start(): void
    {
        $sock = @fsockopen(
            $this->config->host,
            $this->config->port,
            $errno,
            $errstr,
            1
        );
        if (is_resource($sock)) {
            throw new AddressAlreadyException("Address already in use.");
        }
        $address = $this->config->address();
        if ($this->config->getAdvertisedHost() != "") {
            $address = $this->config->getAdvertisedHost();
        }
        $this->actorSystem->getProcessRegistry()
            ->registerAddressResolver(new RemoteHandler($this));
        $this->actorSystem->getProcessRegistry()->setAddress($address);
        $this->endpointManager = new EndpointManager($this, $this->config->isUseWebSocket());
        $this->endpointManager->start();

        $this->server = new WebSocketServer(
            $this->actorSystem->getLogger(),
            $this->config->host,
            $this->config->port
        );
        $this->endpointReader = new RemotingService($this, $this->serializerManager);
        $service = new ServiceContainer($this->endpointReader);
        $this->server->registerHandler($service->name, $service);
        $this->logger()->info("Starting Phluxor remote server", ["address" => $address]);
        \Swoole\Coroutine\go(function () {
            $this->server?->run();
        });
    }

    public function shutdown(bool $graceful = false): void
    {
        if ($graceful) {
            $this->endpointReader?->suspend(true);
            $this->endpointManager?->stop();
            $c = new Channel(1);
            $result = $c->pop(10);
            if ($result) {
                $this->logger()->info("Stopped Phluxor server");
            } else {
                $this->server?->stop();
                $this->server = null;
                $this->logger()->info("Stopped Phluxor server with timeout");
            }
        } else {
            $this->endpointReader?->suspend(true);
            $this->endpointManager?->stop();
            $this->server?->stop();
            $this->logger()->info("Killed Phluxor server");
        }
    }

    public function sendMessage(
        ?ActorSystem\Ref $ref,
        ?ActorSystem\ReadonlyMessageHeaderInterface $header,
        mixed $message,
        ?ActorSystem\Ref $sender,
        int $serializerId
    ): void {
        $this->endpointManager?->remoteDeliver(
            new RemoteDeliver(
                header: $header,
                message: $message,
                target: $ref,
                sender: $sender,
                serializerId: $serializerId
            )
        );
    }

    /**
     * @return LoggerInterface
     */
    public function logger(): LoggerInterface
    {
        return $this->actorSystem->getLogger();
    }

    // Register a known actor props by name
    public function register(string $kind, Props $props): void
    {
        $this->kinds[$kind] = $props;
    }

    /**
     * returns a slice of known actor "Kinds"
     * @return string[]
     */
    public function getKnownKinds(): array
    {
        return array_keys($this->kinds);
    }

    /**
     * @return array<string, Props>
     */
    public function getKinds(): array
    {
        return $this->kinds;
    }

    /**
     * returns a Ref for the activator at the given address
     * @param string $address
     * @return ActorSystem\Ref
     */
    public function activatorForAddress(string $address): ActorSystem\Ref
    {
        return new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
            'address' => $address,
            'id' => 'activator',
        ]));
    }

    /**
     * spawns a remote actor and returns a Future that completes once the actor is started
     * @param string $address
     * @param string $name
     * @param string $kind
     * @param int $timeout
     * @return ActorSystem\Future
     */
    public function spawnFuture(string $address, string $name, string $kind, int $timeout): ActorSystem\Future
    {
        return $this->actorSystem->root()->requestFuture(
            $this->activatorForAddress($address),
            new ActorPidRequest([
                'name' => $name,
                'kind' => $kind,
            ]),
            $timeout
        );
    }

    public function spawn(string $address, string $kind, int $timeout): ActorPidResponse
    {
        return $this->spawnNamed($address, "", $kind, $timeout);
    }

    /**
     * @throws RuntimeException
     */
    public function spawnNamed(string $address, string $name, string $kind, int $timeout): ActorPidResponse
    {
        $result = $this->spawnFuture($address, $name, $kind, $timeout)->result();
        if ($result->error() != null) {
            throw $result->error();
        }
        $msg = $result->value();
        return match (true) {
            $msg instanceof ActorPidResponse => $msg,
            default => throw new RuntimeException("remote: Unknown response when remote activating"),
        };
    }

    public function getEndpointManager(): EndpointManager
    {
        if ($this->endpointManager == null) {
            throw new RuntimeException("EndpointManager is not started");
        }
        return $this->endpointManager;
    }

    public function getSerializerManager(): SerializerManager
    {
        return $this->serializerManager;
    }
}
