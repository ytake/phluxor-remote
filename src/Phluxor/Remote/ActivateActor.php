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

use Phluxor\ActorSystem\Context\ContextInterface;
use Phluxor\ActorSystem\Exception\NameExistsException;
use Phluxor\ActorSystem\Exception\SpawnErrorException;
use Phluxor\ActorSystem\Message\ActorInterface;
use Phluxor\ActorSystem\Message\DetectAutoReceiveMessage;
use Phluxor\ActorSystem\Message\DetectSystemMessage;
use Phluxor\ActorSystem\Message\Started;
use Phluxor\Remote\Exception\PropsErrorException;
use Phluxor\Remote\Message\Ping;
use Phluxor\Remote\Message\Pong;
use Phluxor\Remote\ProtoBuf\ActorPidRequest;
use Phluxor\Remote\ProtoBuf\ActorPidResponse;

use function sprintf;

readonly class ActivateActor implements ActorInterface
{
    public function __construct(
        private Remote $remote
    ) {
    }

    public function receive(ContextInterface $context): void
    {
        $message = $context->message();
        switch (true) {
            case $message instanceof Started:
                $context->logger()->info("Started Activator");
                break;
            case $message instanceof Ping:
                $context->respond(new Pong());
                break;
            case $message instanceof ActorPidRequest:
                if (!isset($this->remote->getKinds()[$message->getKind()])) {
                    $response = new ActorPidResponse([
                        'status_code' => ResponseStatusCode::ERROR
                    ]);
                    $context->respond($response);
                    throw new PropsErrorException(
                        sprintf(
                            "Unknown kind: %s",
                            $message->getKind()
                        )
                    );
                }
                $name = $message->getName();
                if ($name == '') {
                    $name = $context->actorSystem()->getProcessRegistry()->nextId();
                }
                $props = $this->remote->getKinds()[$message->getKind()];
                $result = $context->spawnNamed($props, "Remote$" . $name);
                if (!$result->isError()) {
                    $context->respond(
                        new ActorPidResponse([
                            'pid' => $result->getRef()?->protobufPid(),
                        ])
                    );
                } elseif ($result->isError() instanceof NameExistsException) {
                    $context->respond(
                        new ActorPidResponse([
                            'status_code' => ResponseStatusCode::PROCESS_NAME_ALREADY_EXIST,
                        ])
                    );
                } else {
                    $context->respond(
                        new ActorPidResponse([
                            'status_code' => ResponseStatusCode::ERROR,
                        ])
                    );
                    throw new SpawnErrorException(
                        sprintf(
                            "Error spawning actor: %s",
                            $result->isError()->getMessage()
                        )
                    );
                }
                break;
            case $this->isDefinedMessage($message):
                break;
            default:
                $context->logger()->error(
                    "Activator received unknown message",
                    ['message' => $message]
                );
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
}
