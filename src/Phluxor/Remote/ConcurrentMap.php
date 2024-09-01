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
use Swoole\Lock;

class ConcurrentMap
{
    /** @var array<string|int, mixed> */
    private array $map;
    private Lock $lock;

    public function __construct()
    {
        $this->map = [];
        $this->lock = new Lock(SWOOLE_MUTEX);
    }

    public function get(mixed $key): mixed
    {
        $this->lock->lock();
        $value = $this->map[$key] ?? null;
        $this->lock->unlock();
        return $value;
    }

    public function set(mixed $key, mixed $value): void
    {
        $this->lock->lock();
        $this->map[$key] = $value;
        $this->lock->unlock();
    }

    public function delete(mixed $key): void
    {
        $this->lock->lock();
        unset($this->map[$key]);
        $this->lock->unlock();
    }

    public function has(mixed $key): bool
    {
        $this->lock->lock();
        $exists = isset($this->map[$key]);
        $this->lock->unlock();
        return $exists;
    }

    public function clear(): void
    {
        $this->lock->lock();
        $this->map = [];
        $this->lock->unlock();
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return ConcurrentMapResult
     */
    public function getOrSet(mixed $key, mixed $value): ConcurrentMapResult
    {
        $this->lock->lock();
        if (!isset($this->map[$key])) {
            $this->map[$key] = $value;
            $this->lock->unlock();
            return new ConcurrentMapResult($value, false);
        }
        $existingValue = $this->map[$key];
        $this->lock->unlock();
        return new ConcurrentMapResult($existingValue, true);
    }

    /**
     * @param Closure(mixed, mixed): bool $f
     * @return void
     */
    public function range(Closure $f): void
    {
        $this->lock->lock();
        $snapshot = $this->map;
        $this->lock->unlock();
        foreach ($snapshot as $key => $value) {
            if (!$f($key, $value)) {
                break;
            }
        }
    }
}
