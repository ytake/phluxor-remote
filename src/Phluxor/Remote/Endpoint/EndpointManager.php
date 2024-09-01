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

use Phluxor\ActorSystem\DeadLetterEvent;
use Phluxor\ActorSystem\Dispatcher\SynchronizedDispatcher;
use Phluxor\ActorSystem\Exception\FutureTimeoutException;
use Phluxor\ActorSystem\Props;
use Phluxor\ActorSystem\Ref;
use Phluxor\ActorSystem\Strategy\RestartingStrategy;
use Phluxor\EventStream\Subscription;
use Phluxor\Remote\ActivateActor;
use Phluxor\Remote\ConcurrentMap;
use Phluxor\Remote\Endpoint;
use Phluxor\Remote\Message\EndpointConnectedEvent;
use Phluxor\Remote\Message\EndpointTerminatedEvent;
use Phluxor\Remote\Message\Ping;
use Phluxor\Remote\Message\RemoteDeliver;
use Phluxor\Remote\Message\RemoteTerminate;
use Phluxor\Remote\Message\RemoteUnwatch;
use Phluxor\Remote\Message\RemoteWatch;
use Phluxor\Remote\Remote;
use Swoole\Coroutine\Channel;

class EndpointManager
{
    private bool $stopped = false;
    private ?Subscription $endpointSub = null;
    private ?Ref $endpointSupervisor = null;
    private ?Ref $activator = null;
    private ?ConcurrentMap $connections = null;
    private ?ConcurrentMap $endpointReaderConnections = null;

    public function __construct(
        private Remote $remote,
        private readonly bool $useWebSocket = false,
    ) {
        $this->connections = new ConcurrentMap();
        $this->endpointReaderConnections = new ConcurrentMap();
    }

    public function getRemote(): Remote
    {
        return $this->remote;
    }

    public function start(): void
    {
        $eventStream = $this->remote->actorSystem->getEventStream();
        $this->endpointSub = $eventStream?->subscribeWithPredicate(
            fn(mixed $event) => $this->endpointEvent($event),
            function (mixed $message): bool {
                return match (true) {
                    $message instanceof EndpointConnectedEvent,
                        $message instanceof EndpointTerminatedEvent => true,
                    default => false,
                };
            },
        );
        $this->startActivator();
        $this->startSupervisor();
        $err = $this->waiting(3);
        if ($err != null) {
            throw $err;
        }
        $this->remote->logger()->info("Started EndpointManager");
    }

    /**
     * @param int $seconds
     * @return FutureTimeoutException|null
     */
    private function waiting(int $seconds): null|FutureTimeoutException
    {
        $ctx = $this->remote->actorSystem->root();
        $result = $ctx->requestFuture($this->activator, new Ping(), $seconds)->result();
        if ($result->error() != null) {
            return $result->error();
        }
        return null;
    }

    public function stop(): void
    {
        $this->stopped = true;
        if ($this->endpointSub != null) {
            $this->remote->actorSystem->getEventStream()?->unsubscribe($this->endpointSub);
        }
        $stoppedActivator = $this->stopActivator();
        if ($stoppedActivator != null) {
            $this->remote->logger()->error(
                "stop endpoint activator failed",
                ["error" => $stoppedActivator->getMessage()]
            );
        }
        $stoppedSupervisor = $this->stopSupervisor();
        if ($stoppedSupervisor != null) {
            $this->remote->logger()->error(
                "stop endpoint supervisor failed",
                ["error" => $stoppedSupervisor->getMessage()]
            );
        }
        $this->endpointSub = null;
        $this->connections = null;
        if ($this->endpointReaderConnections != null) {
            $this->endpointReaderConnections->range(function (mixed $key, mixed $value): bool {
                if ($value instanceof Channel) {
                    $channel = $value;
                    $channel->push(true);
                    if (is_string($key)) {
                        $this->endpointReaderConnections?->delete($key);
                        return true;
                    }
                    return false;
                }
                return false;
            });
        }
    }

    private function startActivator(): void
    {
        $props = Props::fromProducer(
            fn() => new ActivateActor($this->remote),
            Props::withGuardian(new RestartingStrategy()),
        );
        $result = $this->remote->actorSystem->root()->spawnNamed($props, "activator");
        if ($result->isError()) {
            throw $result->isError();
        }
        $this->activator = $result->getRef();
    }

    private function stopActivator(): null|FutureTimeoutException
    {
        return $this->remote->actorSystem->root()->stopFuture($this->activator)?->wait();
    }

    private function startSupervisor(): void
    {
        $r = $this->remote;
        $props = Props::fromProducer(
            fn() => new EndpointSupervisor($r, $this->remote->getSerializerManager(), $this->useWebSocket),
            Props::withGuardian(new RestartingStrategy()),
            Props::withSupervisor(new RestartingStrategy()),
            Props::withDispatcher(new SynchronizedDispatcher(300))
        );
        $result = $r->actorSystem->root()->spawnNamed($props, "EndpointSupervisor");
        if ($result->isError()) {
            throw $result->isError();
        }
        $this->endpointSupervisor = $result->getRef();
    }

    private function stopSupervisor(): null|FutureTimeoutException
    {
        return $this->remote->actorSystem->root()->stopFuture($this->endpointSupervisor)?->wait();
    }

    private function endpointEvent(mixed $event): void
    {
        if ($event instanceof EndpointTerminatedEvent) {
            $this->remote->actorSystem->getLogger()->debug(
                "EndpointManager received endpoint terminated event, removing endpoint",
                ["message" => $event]
            );
            $this->removeEndpoint($event);
        } elseif ($event instanceof EndpointConnectedEvent) {
            $endpoint = $this->ensureConnected($event->address);
            $this->remote->actorSystem->root()->send($endpoint?->watcher, $event);
        }
    }

    public function remoteTerminate(RemoteTerminate $message): void
    {
        if ($this->stopped) {
            return;
        }
        $address = $message->watchee->getAddress();
        $endpoint = $this->ensureConnected($address);
        $this->remote->actorSystem->root()->send($endpoint?->watcher, $message);
    }

    public function remoteWatch(RemoteWatch $message): void
    {
        if ($this->stopped) {
            return;
        }
        $address = $message->watchee->getAddress();
        $endpoint = $this->ensureConnected($address);
        $this->remote->actorSystem->root()->send($endpoint?->watcher, $message);
    }

    public function remoteUnwatch(RemoteUnwatch $message): void
    {
        if ($this->stopped) {
            return;
        }
        $address = $message->watchee->getAddress();
        $endpoint = $this->ensureConnected($address);
        $this->remote->actorSystem->root()->send($endpoint?->watcher, $message);
    }

    public function remoteDeliver(RemoteDeliver $message): void
    {
        if ($this->stopped) {
            $this->remote->actorSystem->getEventStream()?->publish(
                new DeadLetterEvent($message->target, $message->message, $message->sender)
            );
            return;
        }
        $address = $message->target?->protobufPid()->getAddress();
        if ($address != null) {
            $endpoint = $this->ensureConnected($address);
            $this->remote->actorSystem->root()->send($endpoint?->writer, $message);
        }
    }

    private function ensureConnected(string $address): ?Endpoint
    {
        if ($this->connections == null) {
            return null;
        }
        $value = $this->connections->get($address);
        if ($value === null) {
            $result = $this->connections->getOrSet($address, new EndpointLazy($this, $address));
            $value = $result->actual;
        }
        if ($value instanceof EndpointLazy) {
            return $value->get();
        }
        return null;
    }

    private function removeEndpoint(EndpointTerminatedEvent $message): void
    {
        if ($this->connections == null) {
            return;
        }
        $value = $this->connections->get($message->address);
        if ($value instanceof EndpointLazy) {
            if ($value->getUnloaded()->cmpset(0, 1)) {
                $this->connections->delete($message->address);
                $endpoint = $value->get();
                $this->remote->logger()->debug(
                    "sending EndpointTerminatedEvent to EndpointWatcher and EndpointWriter",
                    ["address" => $message->address]
                );
                $this->remote->actorSystem->root()->send($endpoint?->watcher, $message);
                $this->remote->actorSystem->root()->send($endpoint?->writer, $message);
            }
        }
    }

    public function getEndpointSupervisor(): ?Ref
    {
        return $this->endpointSupervisor;
    }

    public function getEndpointReaderConnections(): ?ConcurrentMap
    {
        return $this->endpointReaderConnections;
    }
}
