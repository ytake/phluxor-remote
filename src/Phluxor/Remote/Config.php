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

use Closure;
use Phluxor\ActorSystem\Props;

class Config
{
    private string $advertisedHost = '';
    private int $endpointWriterBatchSize = 1000;
    private int $endpointWriterQueueSize = 1000000;
    private int $endpointManagerBatchSize = 1000;
    private int $endpointManagerQueueSize = 1000000;
    private bool $ssl = false;
    private bool $useWebSocket = false;

    /** @var array<string, Props> */
    private array $kinds = [];
    private int $maxRetryCount = 5;

    /**
     * @param string $host
     * @param int $port
     * @param Closure(Config): void ...$options ...$options
     */
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        Closure ...$options
    ) {
        foreach ($options as $option) {
            $option($this);
        }
    }

    public function address(): string
    {
        return sprintf('%s:%d', $this->host, $this->port);
    }

    public function getAdvertisedHost(): string
    {
        return $this->advertisedHost;
    }

    public function isSsl(): bool
    {
        return $this->ssl;
    }

    public static function withSsl(bool $ssl): Closure
    {
        return function (Config $config) use ($ssl) {
            $config->ssl = $ssl;
        };
    }

    /**
     * @param string $address
     * @return Closure(Config): void
     */
    public static function withAdvertisedHost(string $address): Closure
    {
        return function (Config $config) use ($address) {
            $config->advertisedHost = $address;
        };
    }

    public function isUseWebSocket(): bool
    {
        return $this->useWebSocket;
    }

    public static function withUseWebSocket(bool $useWebSocket): Closure
    {
        return function (Config $config) use ($useWebSocket) {
            $config->useWebSocket = $useWebSocket;
        };
    }
    
    public function getEndpointWriterBatchSize(): int
    {
        return $this->endpointWriterBatchSize;
    }

    /**
     * @param int $endpointWriterBatchSize
     * @return Closure(Config): void
     */
    public static function withEndpointWriterBatchSize(int $endpointWriterBatchSize): Closure
    {
        return function (Config $config) use ($endpointWriterBatchSize) {
            $config->endpointWriterBatchSize = $endpointWriterBatchSize;
        };
    }

    public function getEndpointWriterQueueSize(): int
    {
        return $this->endpointWriterQueueSize;
    }

    /**
     * @param int $endpointWriterQueueSize
     * @return Closure(Config): void
     */
    public static function withEndpointWriterQueueSize(int $endpointWriterQueueSize): Closure
    {
        return function (Config $config) use ($endpointWriterQueueSize) {
            $config->endpointWriterQueueSize = $endpointWriterQueueSize;
        };
    }

    public function getEndpointManagerBatchSize(): int
    {
        return $this->endpointManagerBatchSize;
    }

    /**
     * @param int $endpointManagerBatchSize
     * @return Closure(Config): void
     */
    public static function withEndpointManagerBatchSize(int $endpointManagerBatchSize): Closure
    {
        return function (Config $config) use ($endpointManagerBatchSize) {
            $config->endpointManagerBatchSize = $endpointManagerBatchSize;
        };
    }

    public function getEndpointManagerQueueSize(): int
    {
        return $this->endpointManagerQueueSize;
    }

    /**
     * @param int $endpointManagerQueueSize
     * @return Closure(Config): void
     */
    public static function withEndpointManagerQueueSize(int $endpointManagerQueueSize): Closure
    {
        return function (Config $config) use ($endpointManagerQueueSize) {
            $config->endpointManagerQueueSize = $endpointManagerQueueSize;
        };
    }

    /**
     * @return array<string, Props>
     */
    public function getKinds(): array
    {
        return $this->kinds;
    }

    /**
     * @param Kind ...$kinds
     * @return Closure(Config): void
     */
    public static function withKinds(Kind ...$kinds): Closure
    {
        return function (Config $config) use ($kinds) {
            foreach ($kinds as $kind) {
                $config->kinds[$kind->kind] = $kind->props;
            }
        };
    }

    public function getMaxRetryCount(): int
    {
        return $this->maxRetryCount;
    }

    public static function withMaxRetryCount(int $maxRetryCount): Closure
    {
        return function (Config $config) use ($maxRetryCount) {
            $config->maxRetryCount = $maxRetryCount;
        };
    }
}
