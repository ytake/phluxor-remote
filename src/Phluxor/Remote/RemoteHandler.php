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

use Phluxor\ActorSystem\AddressResolverInterface;
use Phluxor\ActorSystem\ProcessRegistryResult;
use Phluxor\ActorSystem\Ref;

readonly class RemoteHandler implements AddressResolverInterface
{
    public function __construct(
        private Remote $remote
    ) {
    }

    public function __invoke(?Ref $pid): ProcessRegistryResult
    {
        if ($pid === null) {
            throw new \RuntimeException('Cannot resolve null pid');
        }
        return new ProcessRegistryResult(new RemoteProcess($pid, $this->remote), true);
    }
}
