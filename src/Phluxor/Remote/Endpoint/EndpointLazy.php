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

use Phluxor\Remote\Endpoint;
use Swoole\Atomic;

class EndpointLazy
{
    private Atomic $unloaded;
    private bool $once = false;
    private ?Endpoint $endpoint = null;

    public function __construct(
        public readonly EndpointManager $manager,
        public readonly string $address
    ) {
        $this->unloaded = new Atomic(0);
    }

    private function connect(): void
    {
        $system = $this->manager->getRemote()->actorSystem;
        $system->getLogger()->debug("connecting to remote address", ["address" => $this->address]);
        $em = $this->manager;
        $rst = $system->root()->requestFuture(
            $em->getEndpointSupervisor(),
            $this->address,
            -1
        )->result();
        $ep = $rst->value();
        if ($ep instanceof Endpoint) {
            $this->set($ep);
        }
    }

    public function set(Endpoint $ep): void
    {
        $this->endpoint = $ep;
    }

    public function get(): ?Endpoint
    {
        if (!$this->once) {
            $this->once = true;
            $this->connect();
        }
        return $this->endpoint;
    }

    public function getUnloaded(): Atomic
    {
        return $this->unloaded;
    }
}
