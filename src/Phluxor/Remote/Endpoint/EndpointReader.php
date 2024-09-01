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

use Google\Protobuf\Internal\Message;
use Google\Protobuf\Internal\RepeatedField;
use Grpc\ServerCallWriter;
use Phluxor\ActorSystem\Message\DetectAutoReceiveMessage;
use Phluxor\ActorSystem\Message\DetectSystemMessage;
use Phluxor\ActorSystem\Message\MessageHeader;
use Phluxor\ActorSystem\ProtoBuf\Pid;
use Phluxor\ActorSystem\ProtoBuf\Terminated;
use Phluxor\ActorSystem\Ref;
use Phluxor\Remote\Message\RemoteTerminate;
use Phluxor\Remote\ProtoBuf\ConnectRequest;
use Phluxor\Remote\ProtoBuf\ConnectResponse;
use Phluxor\Remote\ProtoBuf\DisconnectRequest;
use Phluxor\Remote\ProtoBuf\GetProcessDiagnosticsRequest;
use Phluxor\Remote\ProtoBuf\ListProcessesRequest;
use Phluxor\Remote\ProtoBuf\MessageBatch;
use Phluxor\Remote\ProtoBuf\MessageEnvelope;
use Phluxor\Remote\ProtoBuf\RemoteMessage;
use Phluxor\Remote\ProtoBuf\RemotingStub;
use Phluxor\Remote\ProtoBuf\ServerConnection;
use Phluxor\Remote\Remote;

use Phluxor\Remote\Serializer\RootSerializedInterface;
use Phluxor\Remote\Serializer\SerializerManager;

use function spl_object_id;

class EndpointReader extends RemotingStub
{
    private bool $suspend = false;

    public function __construct(
        private Remote $remote,
        private SerializerManager $serializerManager
    ) {
    }

    public function ListProcesses(
        ListProcessesRequest $request,
        \Grpc\ServerContext $context
    ): ?\Phluxor\Remote\ProtoBuf\ListProcessesResponse {
        throw new \RuntimeException('Not implemented');
    }

    public function GetProcessDiagnostics(
        GetProcessDiagnosticsRequest $request,
        \Grpc\ServerContext $context
    ): ?\Phluxor\Remote\ProtoBuf\GetProcessDiagnosticsResponse {
        throw new \RuntimeException('Not implemented');
    }

    public function Receive(
        \Grpc\ServerCallReader $reader,
        ServerCallWriter $writer,
        \Grpc\ServerContext $context
    ): void {
        $disconnectChannel = new \Swoole\Coroutine\Channel(1);
        $edpm = $this->remote->getEndpointManager();
        if ($edpm === null) {
            throw new \RuntimeException('EndpointManager not found');
        }
        if ($edpm->getEndpointReaderConnections() === null) {
            throw new \RuntimeException('EndpointReaderConnections not found');
        }
        $edpm->getEndpointReaderConnections()->set(spl_object_id($writer), $disconnectChannel);
        try {
            \Swoole\Coroutine\go(function () use ($disconnectChannel, $writer, $edpm) {
                if ($disconnectChannel->pop()) {
                    $this->remote->logger()->debug("EndpointReader is telling to remote that it's leaving");
                    $rm = new RemoteMessage();
                    $rm->setDisconnectRequest(new DisconnectRequest());
                    $writer->write($rm);
                } else {
                    $edpm->getEndpointReaderConnections()->delete(spl_object_id($writer));
                    $this->remote->logger()->debug("EndpointReader removed active endpoint from endpointManager");
                }
            });

            while (true) {
                $message = $reader->read();
                if ($message === null) {
                    $this->remote->logger()->info("EndpointReader stream closed");
                    $disconnectChannel->push(false);
                    return;
                }
                if ($message === false) {
                    $this->remote->logger()->info(
                        "EndpointReader failed to read",
                        ['error' => 'Error receiving message']
                    );
                    return;
                }

                if ($this->suspend) {
                    continue;
                }
                if ($message instanceof Message) {
                    switch (true) {
                        case $message instanceof ConnectRequest:
                            $this->remote->logger()->debug(
                                "EndpointReader received connect request",
                                ['message' => $message]
                            );
                            $err = $this->onConnectRequest($writer, $message);
                            if ($err) {
                                $this->remote->logger()->error("EndpointReader failed to handle connect request");
                                return;
                            }
                            break;

                        case $message instanceof MessageBatch:
                            $err = $this->onMessageBatch($message);
                            if ($err) {
                                $this->remote->logger()->error(
                                    "EndpointReader failed to handle message batch",
                                    ['error' => $err]
                                );
                                return;
                            }
                            break;
                        default:
                            $this->remote->logger()->notice("EndpointReader received unknown message type");
                    }
                }
            }
        } finally {
            $disconnectChannel->close();
        }
    }

    public function onConnectRequest(ServerCallWriter $writer, ConnectRequest $request): bool
    {
        switch (true) {
            case !is_null($request->getServerConnection()):
                $sc = $request->getServerConnection();
                $this->onServerConnection($writer, $sc);
                return true;
            case !is_null($request->getClientConnection()):
                $this->remote->logger()->error("ClientConnection not implemented");
                return false;
            default:
                $this->remote->logger()->error("EndpointReader received unknown connection type");
                return false;
        }
    }

    public function onMessageBatch(MessageBatch $messageBatch): bool
    {
        /** @var ?Pid $sender */
        $sender = null;
        /** @var ?Pid $target */
        $target = null;
        /** @var MessageEnvelope $envelope */
        foreach ($messageBatch->getEnvelopes() as $envelope) {
            $data = $envelope->getMessageData();
            $sender = $this->deserializeSender(
                $sender,
                $envelope->getSender(),
                $envelope->getSenderRequestId(),
                $messageBatch->getSenders()
            );
            $target = $this->deserializeTarget(
                $target,
                $envelope->getTarget(),
                $envelope->getTargetRequestId(),
                $messageBatch->getTargets()
            );
            if ($target === null) {
                $this->remote->logger()->error(
                    "EndpointReader received message with unknown target.",
                    [
                        'target' => $envelope->getTarget(),
                        'targetRequestId' => $envelope->getTargetRequestId()
                    ]
                );
                return false;
            }
            $deserialized = $this->serializerManager->deserialize(
                $data,
                $messageBatch->getTypeNames()[$envelope->getTypeId()], // @phpstan-ignore-line
                $envelope->getSerializerId()
            );
            if ($deserialized->exception != null) {
                $this->remote->logger()->error(
                    "EndpointReader failed to deserialize message",
                    ['error' => $deserialized->exception]
                );
                return false;
            }
            $message = $deserialized->message;
            if ($message instanceof RootSerializedInterface) {
                $result = $message->deserialize();
                if ($result->exception != null) {
                    $this->remote->logger()->error(
                        "EndpointReader failed to deserialize message",
                        ['error' => $result->exception]
                    );
                    return false;
                }
            }
            switch (true) {
                case $message instanceof Terminated:
                    $this->remote->getEndpointManager()?->remoteTerminate(
                        new RemoteTerminate($message->getWho(), $target) // @phpstan-ignore-line
                    );
                    break;
                case $this->isSystemMessage($message):
                    $ref = $this->remote->actorSystem->getProcessRegistry()->getLocal($target->getId());
                    if ($ref->isProcess()) {
                        $ref->getProcess()->sendSystemMessage(new Ref($target), $message);
                    }
                    break;
                default:
                    /** @var array<string, string> $header */
                    $header = [];
                    if ($sender === null) {
                        if ($envelope->getMessageHeader() === null) {
                            $this->remote->actorSystem->root()->send(new Ref($target), $message);
                            break;
                        }
                    }

                    if ($envelope->getMessageHeader() != null) {
                        $header = $envelope->getMessageHeader()->getHeaderData();
                    }
                    $localEnvelope = new \Phluxor\ActorSystem\Message\MessageEnvelope(
                        new MessageHeader($header), // @phpstan-ignore-line
                        $message,
                        new Ref($target),
                    );
                    $this->remote->actorSystem->root()->send(new Ref($target), $localEnvelope);
                    break;
            }
        }
        return true;
    }

    /**
     * @param Pid|null $pid
     * @param int $index
     * @param int $requestId
     * @param RepeatedField $arr
     * @return Pid|null
     */
    function deserializeSender(
        ?Pid $pid,
        int $index,
        int $requestId,
        RepeatedField $arr
    ): ?Pid {
        if ($index === 0) {
            $pid = null;
        } else {
            /** @var Pid $pid */
            $pid = $arr[$index - 1];
            if ($requestId > 0) {
                if ($pid != null) {
                    $pid = $this->cloneRef($pid);
                    $pid->setRequestId($requestId);
                }
            }
        }
        return $pid;
    }

    /**
     * @param Pid|null $pid
     * @param int $index
     * @param int $requestId
     * @param RepeatedField $arr
     * @return Pid|null
     */
    private function deserializeTarget(
        ?Pid $pid,
        int $index,
        int $requestId,
        RepeatedField $arr
    ): ?Pid {
        /** @var Pid $pid */
        $pid = $arr[$index];
        // if request id is used.
        // make sure to clone the Ref first, so we don't corrupt the lookup
        if ($requestId > 0) {
            if ($pid != null) {
                $pid = $this->cloneRef($pid);
                $pid->setRequestId($requestId);
            }
        }
        return $pid;
    }

    private function onServerConnection(ServerCallWriter $writer, ServerConnection $sc): void
    {
        if ($this->remote->getBlockList()->isBlocked($sc->getSystemId())) {
            $this->remote->logger()->debug("EndpointReader is blocked.");
            $rm = new RemoteMessage();
            $rm->setConnectResponse(new ConnectResponse([
                'member_id' => $this->remote->actorSystem->getId(),
                'blocked' => true
            ]));
            $writer->write($rm);
            return;
        }
        $rm = new RemoteMessage();
        $rm->setConnectResponse(new ConnectResponse([
            'member_id' => $this->remote->actorSystem->getId(),
            'blocked' => false
        ]));
        $writer->write($rm);
    }

    private function cloneRef(Pid $ref): Pid
    {
        return new Pid([
            'address' => $ref->getAddress(),
            'id' => $ref->getId(),
            'request_id' => $ref->getRequestId()
        ]);
    }

    public function suspend(bool $to): void
    {
        $this->suspend = $to;
    }

    private function isSystemMessage(mixed $msg): bool
    {
        $expect = new DetectSystemMessage($msg);
        if ($expect->isMatch()) {
            return true;
        }
        return false;
    }
}
