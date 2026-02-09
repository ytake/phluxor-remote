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

use Swoole\Lock;

use const SWOOLE_MUTEX;

class BlockList
{
    private Lock $lock;
    private ImmutableSet $blockedMembers;

    public function __construct()
    {
        $this->lock = new Lock(SWOOLE_MUTEX);
        $this->blockedMembers = new ImmutableSet();
    }

    public function blockedMembers(): ImmutableSet
    {
        return $this->blockedMembers;
    }

    public function block(string ...$memberIDs): void
    {
        $this->lock->lock();
        $this->blockedMembers = $this->blockedMembers->addRange($memberIDs);
        $this->lock->unlock();
    }

    public function isBlocked(string $memberID): bool
    {
        $this->lock->lock();
        try {
            return $this->blockedMembers->contains($memberID);
        } finally {
            $this->lock->unlock();
        }
    }

    public function len(): int
    {
        $this->lock->lock();
        $size = $this->blockedMembers->size();
        $this->lock->unlock();
        return $size;
    }
}
