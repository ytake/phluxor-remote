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

use Phluxor\ActorSystem\Behavior;
use Phluxor\ActorSystem\Context\ContextInterface;
use Phluxor\ActorSystem\Message\ActorInterface;
use Phluxor\ActorSystem\Message\DetectAutoReceiveMessage;
use Phluxor\ActorSystem\Message\DetectSystemMessage;
use Phluxor\ActorSystem\Message\ReceiveFunction;
use Phluxor\ActorSystem\Message\Started;
use Phluxor\ActorSystem\ProtoBuf\Terminated;
use Phluxor\ActorSystem\ProtoBuf\TerminatedReason;
use Phluxor\ActorSystem\ProtoBuf\Watch;
use Phluxor\ActorSystem\Ref;
use Phluxor\ActorSystem\RefSet;
use Phluxor\Remote\Message\EndpointConnectedEvent;
use Phluxor\Remote\Message\EndpointTerminatedEvent;
use Phluxor\Remote\Message\RemoteTerminate;
use Phluxor\Remote\Message\RemoteUnwatch;
use Phluxor\Remote\Message\RemoteWatch;
use Phluxor\Remote\Remote;

class EndpointWatcher implements ActorInterface
{
    private Behavior $behavior;

    /** @var array<string, RefSet> */
    private array $watched = [];

    public function __construct(
        private string $address,
        private Remote $remote,
    ) {
        $this->behavior = new Behavior();
        $this->behavior->become(
            new ReceiveFunction(
                fn(ContextInterface $context) => $this->connected($context)
            )
        );
    }

    private function initialize(): void
    {
        $this->remote->logger()->info("Started EndpointWatcher", ["address" => $this->address]);
        $this->watched = [];
    }

    public function receive(ContextInterface $context): void
    {
        $this->behavior->receive($context);
    }

    private function connected(ContextInterface $context): void
    {
        $message = $context->message();
        switch (true) {
            case $message instanceof Started:
                $this->initialize();
                break;
            case $this->isDefinedMessage($message):
            case $message instanceof EndpointConnectedEvent:
                // pass
                break;
            case $message instanceof EndpointTerminatedEvent:
                $this->remote->logger()->info(
                    "EndpointWatcher handling terminated",
                    ["address" => $this->address, "watched" => count($this->watched)]
                );
                foreach ($this->watched as $id => $refSet) {
                    $result = $this->remote->actorSystem->getProcessRegistry()->getLocal($id);
                    if ($result->isProcess()) {
                        $process = $result->getProcess();
                        $refSet->forEach(function (int $i, Ref $ref) use ($id, $process) {
                            $terminated = new Terminated([
                                'who' => $ref->protobufPid(),
                                'why' => TerminatedReason::AddressTerminated,
                            ]);
                            $watcher = $this->remote->actorSystem->newLocalAddress($id);
                            $process->sendSystemMessage($watcher, $terminated);
                        });
                    }
                }
                // clear watched
                $this->watched = [];
                $this->behavior->become(
                    new ReceiveFunction(
                        fn(ContextInterface $context) => $this->terminated(
                            $context
                        )
                    )
                );
                $context->stop($context->self());
                break;
            case $message instanceof RemoteWatch:
                if (isset($this->watched[(string)$message->watcher])) {
                    $this->watched[(string)$message->watcher]->add($message->watchee);
                } else {
                    $this->watched[(string)$message->watcher] = new RefSet($message->watchee);
                }
                $watch = new Watch([
                    'watcher' => $message->watcher,
                ]);
                $this->remote->sendMessage($message->watchee, null, $watch, null, -1);
                break;
            default:
                $this->remote->logger()->error(
                    "EndpointWatcher received unknown message",
                    ["address" => $this->address, "message" => $message]
                );
                break;
        }
    }

    private function isDefinedMessage(mixed $msg): bool
    {
        foreach (
            [
                new DetectAutoReceiveMessage($msg),
                new DetectSystemMessage($msg),
            ] as $expect
        ) {
            if ($expect->isMatch()) {
                return true;
            }
        }
        return false;
    }

    private function terminated(ContextInterface $context): void
    {
        $message = $context->message();
        switch (true) {
            case $message instanceof RemoteWatch:
                $result = $this->remote->actorSystem->getProcessRegistry()->getLocal((string)$message->watcher);
                if ($result->isProcess()) {
                    $terminated = new Terminated([
                        'who' => $message->watchee,
                        'why' => TerminatedReason::AddressTerminated,
                    ]);
                    $result->getProcess()->sendSystemMessage($message->watcher, $terminated);
                }
                break;
            case $message instanceof RemoteTerminate:
            case $message instanceof EndpointTerminatedEvent:
            case $message instanceof RemoteUnwatch:
                $this->remote->logger()->error(
                    "EndpointWatcher receive message for already terminated endpoint",
                    ["address" => $this->address, "message" => $message]
                );
                break;
            default:
                $this->remote->logger()->error(
                    "EndpointWatcher received unknown message",
                    ["address" => $this->address, "message" => $message]
                );
        }
    }
}
