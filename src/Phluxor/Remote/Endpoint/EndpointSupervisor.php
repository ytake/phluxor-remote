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

namespace Phluxor\Remote\Endpoint;

use Phluxor\ActorSystem;
use Phluxor\ActorSystem\Context\ContextInterface;
use Phluxor\ActorSystem\Message\ActorInterface;
use Phluxor\ActorSystem\Ref;
use Phluxor\ActorSystem\SupervisorInterface;
use Phluxor\ActorSystem\SupervisorStrategyInterface;
use Phluxor\Remote\Endpoint;
use Phluxor\Remote\Remote;
use Phluxor\Remote\Serializer\SerializerManager;

readonly class EndpointSupervisor implements ActorInterface, SupervisorStrategyInterface
{
    public function __construct(
        private Remote $remote,
        private SerializerManager $serializerManager,
        private bool $useWebSocket = false,
    ) {
    }

    public function receive(ContextInterface $context): void
    {
        $message = $context->message();
        if (is_string($message)) {
            $context->logger()->debug(
                "EndpointSupervisor spawning EndpointWriter and EndpointWatcher",
                ['address' => $message]
            );
            $endpoint = new Endpoint(
                writer: $this->spawnEndpointWriter($this->remote, $message, $context),
                watcher: $this->spawnEndpointWatcher($this->remote, $message, $context)
            );
            $context->logger()->debug(
                "id",
                ['ewr' => (string)$endpoint->writer, 'ewa' => (string)$endpoint->watcher]
            );
            $context->respond($endpoint);
        }
    }

    public function handleFailure(
        ActorSystem $actorSystem,
        SupervisorInterface $supervisor,
        Ref $child,
        ActorSystem\Child\RestartStatistics $restartStatistics,
        mixed $reason,
        mixed $message
    ): void {
        $actorSystem->getLogger()->debug(
            "EndpointSupervisor handling failure",
            ['reason' => $reason, 'message' => $message]
        );
        $supervisor->stopChildren($child);
    }

    private function spawnEndpointWriter(
        Remote $remote,
        string $address,
        ContextInterface $context
    ): ?Ref {
        $props = ActorSystem\Props::fromProducer(
            $this->detectEndpointWriterProducer($remote, $address),
            ActorSystem\Props::withMailboxProducer(
                new EndpointWriterMailboxProducer(
                    $remote->config->getEndpointWriterBatchSize(),
                    $remote->config->getEndpointWriterQueueSize()
                )
            )
        );
        return $context->spawn($props);
    }

    private function detectEndpointWriterProducer(
        Remote $remote,
        string $address
    ): ActorSystem\Message\ProducerInterface {
        if ($this->useWebSocket) {
            return new Endpoint\WebSocket\EndpointWriterProducer($remote, $remote->config, $this->serializerManager);
        }
        return new EndpointWriterProducer($this->remote, $address, $this->remote->config, $this->serializerManager);
    }

    private function spawnEndpointWatcher(
        Remote $remote,
        string $address,
        ContextInterface $context
    ): ?Ref {
        return $context->spawn(
            ActorSystem\Props::fromProducer(
                fn() => new EndpointWatcher($address, $remote),
            )
        );
    }
}
