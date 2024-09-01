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

use Phluxor\ActorSystem\Mailbox\MailboxInterface;
use Phluxor\ActorSystem\Mailbox\MailboxProducerInterface;
use Phluxor\Buffer\Queue as RingBufferQueue;
use Phluxor\Mspc\Queue as MpscQueue;

readonly class EndpointWriterMailboxProducer implements MailboxProducerInterface
{
    public function __construct(
        private int $batchSize,
        private int $initialSize,
    ) {
    }

    public function __invoke(): MailboxInterface
    {
        return new EndpointWriterMailbox(
            $this->batchSize,
            new RingBufferQueue($this->initialSize),
            new MpscQueue(),
        );
    }
}
