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

use Exception;
use Phluxor\WebSocket\RequestHandlerInterface;
use Phluxor\WebSocket\Server;
use Psr\Log\LoggerInterface;

readonly class WebSocketServer
{
    private Server $server;

    public function __construct(
        LoggerInterface $logger,
        string $host,
        int $port = 0,
    ) {
        $this->server = new Server($logger, $host, $port);
    }

    public function run(): void
    {
        $this->server->start();
    }

    public function stop(): void
    {
        $this->server->stop();
    }

    /**
     * @throws Exception
     */
    public function registerHandler(string $name, RequestHandlerInterface $handler): self
    {
        $this->server->registerService($name, $handler);
        return $this;
    }
}
