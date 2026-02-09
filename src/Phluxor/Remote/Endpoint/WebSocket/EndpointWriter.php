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

namespace Phluxor\Remote\Endpoint\WebSocket;

use Exception;
use Phluxor\ActorSystem\Context\ContextInterface;
use Phluxor\ActorSystem\Message\ActorInterface;
use Phluxor\ActorSystem\Message\DetectAutoReceiveMessage;
use Phluxor\ActorSystem\Message\DetectSystemMessage;
use Phluxor\ActorSystem\Message\Restarting;
use Phluxor\ActorSystem\Message\Started;
use Phluxor\ActorSystem\Message\Stopped;
use Phluxor\ActorSystem\ProtoBuf\Pid;
use Phluxor\Remote\Config;
use Phluxor\Remote\Exception\EndpointWriterInvalidConnectException;
use Phluxor\Remote\Message\EndpointConnectedEvent;
use Phluxor\Remote\Message\EndpointTerminatedEvent;
use Phluxor\Remote\Message\RemoteDeliver;
use Phluxor\Remote\Message\RestartAfterConnectFailure;
use Phluxor\Remote\ProtoBuf\ConnectRequest;
use Phluxor\Remote\ProtoBuf\MessageBatch;
use Phluxor\Remote\ProtoBuf\MessageEnvelope;
use Phluxor\Remote\ProtoBuf\MessageHeader;
use Phluxor\Remote\ProtoBuf\RemoteMessage;
use Phluxor\Remote\ProtoBuf\ServerConnection;
use Phluxor\Remote\Remote;
use Phluxor\Remote\Serializer\RootSerializableInterface;
use Phluxor\Remote\Serializer\SerializerManager;
use Phluxor\Remote\WebSocket\ProtoBuf\RemotingClient;
use Swoole\Coroutine\Channel;

class EndpointWriter implements ActorInterface
{
    private Channel $errorChannel;
    private Channel $clientClose;
    private bool $stopping = false;

    public function __construct(
        private readonly Config $config,
        private RemotingClient $client,
        private readonly string $address,
        private readonly Remote $remote,
        private readonly SerializerManager $serializerManager
    ) {
        $this->errorChannel = new Channel(1);
        $this->clientClose = new Channel(1);
    }

    private function address(): string
    {
        return $this->address;
    }

    /**
     * @throws Exception|\Throwable
     */
    public function receive(ContextInterface $context): void
    {
        $message = $context->message();
        switch (true) {
            case $message instanceof Started:
                $this->initialize();
                break;
            case $message instanceof Stopped:
                $this->remote->logger()->debug(
                    "WebSocket.EndpointWriter stopped",
                    ["address" => $this->address()]
                );
                $this->closeClientConn();
                break;
            case $message instanceof Restarting:
                $this->remote->logger()->debug(
                    "WebSocket.EndpointWriter restarting",
                    ["address" => $this->address()]
                );
                $this->closeClientConn();
                break;
            case $message instanceof EndpointTerminatedEvent:
                $this->remote->logger()->debug(
                    "WebSocket.EndpointWriter received EndpointTerminatedEvent, stopping actor",
                    ["address" => $this->address()]
                );
                $context->stop($context->self());
                break;
            case $message instanceof RestartAfterConnectFailure:
                $this->remote->logger()->debug(
                    "WebSocket.EndpointWriter initiating self-restart after failing to connect and a delay",
                    ["address" => $this->address()]
                );
                break;
            case $this->isDefinedMessage($message):
                // Do nothing
                break;
            case is_array($message):
                $this->sendEnvelope($message, $context);
                break;
            default:
                $this->remote->logger()->error(
                    "WebSocket.EndpointWriter got invalid message",
                    [
                        "fromAddress" => $this->address(),
                        'type' => $message,
                    ]
                );
        }
    }

    private function closeClientConn(): void
    {
        $this->stopping = true;
        $this->remote->logger()->info(
            "WebSocket.EndpointWriter closing connection",
            ["address" => $this->address()]
        );
        $this->client->close();
        $this->errorChannel->close();
        $this->clientClose->close();
    }

    /**
     * @param list<RemoteDeliver|EndpointTerminatedEvent> $message
     * @param ContextInterface $context
     * @return void
     * @throws Exception
     */
    private function sendEnvelope(array $message, ContextInterface $context): void
    {
        $envelopes = [];
        $typeNames = [];
        $typeNamesArr = [];
        $targetNames = [];
        $targetNamesArr = [];
        $senderNames = [];
        $senderNamesArr = [];
        $serializerId = 0;

        foreach ($message as $tmp) {
            if ($tmp instanceof EndpointTerminatedEvent) {
                $this->remote->logger()->debug(
                    "Handling array wrapped terminate event",
                    [
                        'address' => $this->address(),
                        'message' => $tmp
                    ]
                );
                $context->stop($context->self());
                return;
            }
            if (!$tmp instanceof RemoteDeliver) {
                continue;
            }
            $rd = $tmp;
            if ($rd->header === null || $rd->header->length() === 0) {
                $header = null;
            } else {
                $header = new MessageHeader([
                    'header_data' => $rd->header->toMap()
                ]);
            }
            $message = $rd->message;
            if ($message instanceof RootSerializableInterface) {
                try {
                    $message = $message->serialize();
                } catch (\Exception $err) {
                    $this->remote->logger()->error("EndpointWriter failed to serialize message", [
                        'address' => $this->address(),
                        'error' => $err,
                        'message' => $message
                    ]);
                    continue;
                }
            }
            $serialized = $this->serializerManager->serialize($message, $serializerId);
            if ($serialized->exception !== null) {
                $this->remote->logger()->error("EndpointWriter failed to serialize message", [
                    'address' => $this->address(),
                    'error' => $serialized->exception,
                    'message' => $message
                ]);
                continue;
            }
            if ($rd->target === null) {
                $this->remote->logger()->error("EndpointWriter failed to send message, no target", [
                    'address' => $this->address(),
                    'message' => $message
                ]);
                continue;
            }
            $serializedResult = $serialized->typeNameResult;
            $lookupResult = $this->addToLookup($typeNames, $serializedResult->name, $typeNamesArr);
            $typeNamesArr = $lookupResult['lookup'];
            $targetLookupResult = $this->addToTargetLookup($targetNames, $rd->target->protobufPid(), $targetNamesArr);
            $targetNamesArr = $targetLookupResult['lookup'];
            $targetRequestId = $rd->target->protobufPid()->getRequestId();
            $senderLookupResult = $this->addToSenderLookup($senderNames, $rd->sender?->protobufPid(), $senderNamesArr);
            $senderNamesArr = $senderLookupResult['lookup'];
            $senderRequestId = $rd->sender !== null ? $rd->sender->protobufPid()->getRequestId() : 0;
            $envelopes[] = new MessageEnvelope([
                'message_header' => $header,
                'message_data' => $serialized->serialized,
                'sender' => $senderLookupResult['id'],
                'target' => $targetLookupResult['id'],
                'type_id' => $lookupResult['id'],
                'serializer_id' => $serializerId,
                'target_request_id' => $targetRequestId,
                'sender_request_id' => $senderRequestId
            ]);
        }
        if (count($envelopes) === 0) {
            return;
        }
        $rm = new RemoteMessage();
        $mb = new MessageBatch();
        $mb->setTypeNames($typeNamesArr)
            ->setTargets($targetNamesArr)
            ->setSenders($senderNamesArr)
            ->setEnvelopes($envelopes);
        try {
            $this->client->Receive($rm->setMessageBatch($mb));
        } catch (\Throwable $err) {
            $context->stash();
            $this->remote->logger()->error(
                sprintf("EndpointWriter failed to send message: %s", $err->getMessage()),
                [
                    'address' => $this->address(),
                    'error' => $err,
                    'message' => $message
                ]
            );
            $this->stopping = true;
            $terminated = new EndpointTerminatedEvent($this->address());
            $this->remote->actorSystem->getEventStream()?->publish($terminated);
            $context->stop($context->self());
        }
    }

    /**
     * @throws Exception
     * @throws \Throwable
     */
    private function initialize(): void
    {
        $this->remote->logger()->info(
            "Started WebSocket.EndpointWriter. connecting",
            ["address" => $this->address()]
        );
        $retry = $this->config->getMaxRetryCount();
        for ($i = 0; $i < $retry; $i++) {
            try {
                $this->initializeInternal();
                $this->remote->logger()->info(
                    "WebSocket.EndpointWriter connected",
                    ["address" => $this->address()]
                );
                return;
            } catch (Exception $e) {
                $this->remote->logger()->error(
                    "WebSocket.EndpointWriter failed to connect",
                    [
                        "address" => $this->address(),
                        'error' => $e->getMessage(),
                        'retry' => $i + 1,
                    ]
                );
                \Swoole\Coroutine::sleep(1);
            }
        }
        $terminated = new EndpointTerminatedEvent($this->address());
        $this->remote->actorSystem->getEventStream()?->publish($terminated);
    }

    /**
     * @throws Exception
     */
    private function initializeInternal(): void
    {
        $cr = new ConnectRequest();
        $rm = new RemoteMessage();
        $rm->setConnectRequest($cr->setServerConnection(new ServerConnection([
            'SystemId' => $this->remote->actorSystem->getId(),
            'Address' => $this->remote->actorSystem->address(),
        ])));
        if (!$this->client instanceof RemotingClient) {
            $this->remote->logger()->error(
                "WebSocket.EndpointWriter failed to create client",
                ["fromAddress" => $this->address()]
            );
            return;
        }
        $receive = $this->client->Receive($rm);
        if ($receive === null) {
            throw new EndpointWriterInvalidConnectException("No connect response received");
        }
        switch (true) {
            case $receive->hasConnectResponse():
                $this->remote->logger()->debug(
                    "Received connect response",
                    ["fromAddress" => $this->address()]
                );
                break;
            default:
                $this->remote->logger()->error(
                    "WebSocket.EndpointWriter got invalid connect response",
                    [
                        "fromAddress" => $this->address(),
                    ]
                );
                throw new EndpointWriterInvalidConnectException("Invalid connect response");
        }
        go(function () use ($rm) {
            while (true) {
                if ($this->stopping) {
                    break;
                }
                if ($this->client->hasConnectionError()) {
                    $this->errorChannel->close();
                    $this->clientClose->close();
                    $this->client->close();
                    break;
                }
                try {
                    $this->client->Receive($rm);
                } catch (\Throwable $err) {
                    $this->errorChannel->push($err);
                    break;
                }
            }
        });
        $connected = new EndpointConnectedEvent($this->address());
        $this->remote->actorSystem->getEventStream()?->publish($connected);
    }

    /**
     * TODO move to trait
     * @param mixed $msg
     * @return bool
     */
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

    /**
     * @param array<string, int> $m
     * @param string $name
     * @param string[] $a
     * @return array{id: int, lookup: string[]}
     */
    private function addToLookup(array &$m, string $name, array $a): array
    {
        $max = count($m);
        $id = $m[$name] ?? null;
        if ($id === null) {
            $m[$name] = $max;
            $id = $max;
            $a[] = $name;
        }
        return ['id' => $id, 'lookup' => $a];
    }

    /**
     * @param array<string, int> $m
     * @param Pid $pid
     * @param Pid[] $arr
     * @return array{id: int, lookup: Pid[]}
     */
    private function addToTargetLookup(array &$m, Pid $pid, array $arr): array
    {
        $max = count($m);
        $key = $pid->getAddress() . "/" . $pid->getId();
        $id = $m[$key] ?? null;
        if ($id === null) {
            $c = clone $pid;
            $c->setRequestId(0);
            $m[$key] = $max;
            $id = $max;
            $arr[] = $c;
        }
        return ['id' => $id, 'lookup' => $arr];
    }

    /**
     * @param array<string, int> $m
     * @param ?Pid $pid
     * @param Pid[] $arr
     * @return array{id: int, lookup: Pid[]}
     */
    private function addToSenderLookup(array &$m, ?Pid $pid, array $arr): array
    {
        if ($pid === null) {
            return ['id' => 0, 'lookup' => $arr];
        }
        $max = count($m);
        $key = $pid->getAddress() . "/" . $pid->getId();
        $id = $m[$key] ?? null;
        if ($id === null) {
            $c = clone $pid;
            $c->setRequestId(0);
            $m[$key] = $max;
            $id = $max;
            $arr[] = $c;
        }
        return ['id' => $id + 1, 'lookup' => $arr];
    }
}
