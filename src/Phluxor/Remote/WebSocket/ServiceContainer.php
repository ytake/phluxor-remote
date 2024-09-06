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

namespace Phluxor\Remote\WebSocket;

use Exception;
use Phluxor\Remote\ProtoBuf\RemoteMessage;
use Phluxor\WebSocket\Exception\InvokeException;
use Phluxor\WebSocket\MessageInterface;
use Phluxor\WebSocket\Request;
use Phluxor\WebSocket\RequestHandlerInterface;
use Phluxor\WebSocket\ServiceInterface;
use Phluxor\WebSocket\Status;
use Phluxor\WebSocket\Stream;
use TypeError;

final readonly class ServiceContainer implements RequestHandlerInterface
{
    public string $name;

    /**
     * @param ServiceInterface $service
     * @throws Exception
     */
    public function __construct(
        private ServiceInterface $service
    ) {
        $this->name = $service::NAME;
    }

    /**
     * @throws Exception
     */
    public function handle(Request $request): ?MessageInterface
    {
        $method = $request->method;
        $context = $request->getContext();
        /** @var callable $callable */
        $callable = [$this->service, $method];
        try {
            $callable($context, new Stream($request, new RemoteMessage()));
        } catch (TypeError $e) {
            throw new InvokeException($e->getMessage(), Status::INTERNAL, $e);
        }
        return null;
    }
}
