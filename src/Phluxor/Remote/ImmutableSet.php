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

class ImmutableSet
{
    /**
     * @param array<string, bool> $set
     */
    public function __construct(
        private array $set = []
    ) {
    }

    public function add(string $value): self
    {
        $newSet = clone $this;
        $newSet->set[$value] = true;
        return $newSet;
    }

    /**
     * @param string[] $values
     * @return $this
     */
    public function addRange(array $values): self
    {
        $newSet = clone $this;
        foreach ($values as $value) {
            $newSet->set[$value] = true;
        }
        return $newSet;
    }

    /**
     * @param string $value
     * @return bool
     */
    public function contains(string $value): bool
    {
        return isset($this->set[$value]);
    }

    /**
     * @return int
     */
    public function size(): int
    {
        return count($this->set);
    }
}
