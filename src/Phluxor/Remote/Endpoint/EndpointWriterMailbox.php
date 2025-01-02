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

use Closure;
use Phluxor\ActorSystem\Dispatcher\DispatcherInterface;
use Phluxor\ActorSystem\Mailbox\MailboxInterface;
use Phluxor\ActorSystem\Mailbox\MessageInvokerInterface;
use Phluxor\ActorSystem\Message\ResumeMailbox;
use Phluxor\ActorSystem\Message\SuspendMailbox;
use Phluxor\ActorSystem\QueueResult;
use Phluxor\Buffer\Queue;
use Phluxor\Mspc\Queue as MpscQueue;
use Swoole\Atomic;
use Throwable;

class EndpointWriterMailbox implements MailboxInterface
{
    private const int IDLE = 0;
    private const int RUNNING = 1;

    private const int MAILBOX_HAS_NO_MESSAGES = 0;
    private const int MAILBOX_HAS_MORE_MESSAGES = 1;

    private Atomic $schedulerStatus;
    private Atomic $hasMoreMessages;
    private Atomic $suspended;
    private DispatcherInterface|null $dispatcher;
    private MessageInvokerInterface|null $invoker;

    public function __construct(
        private readonly int $batchSize,
        private readonly Queue $userMailbox,
        private readonly MpscQueue $systemMailbox,
    ) {
        $this->suspended = new Atomic(0);
        $this->schedulerStatus = new Atomic(self::IDLE);
        $this->hasMoreMessages = new Atomic(self::MAILBOX_HAS_NO_MESSAGES);
    }

    public function postUserMessage(mixed $message): void
    {
        $this->userMailbox->push($message);
        $this->schedule();
    }

    public function postSystemMessage(mixed $message): void
    {
        $this->systemMailbox->push($message);
        $this->schedule();
    }

    public function start(): void
    {
        // noop
    }

    private function schedule(): void
    {
        $this->hasMoreMessages->set(self::MAILBOX_HAS_MORE_MESSAGES); // we have more messages to process
        if ($this->schedulerStatus->cmpset(self::IDLE, self::RUNNING)) {
            $this->dispatcher?->schedule($this->processMessage());
        }
    }

    private function processMessage(): Closure
    {
        $this->hasMoreMessages->set(self::MAILBOX_HAS_MORE_MESSAGES);
        return function () {
            process:
            $this->run();
            $this->schedulerStatus->set(self::IDLE);
            if ($this->hasMoreMessages->cmpset(self::MAILBOX_HAS_MORE_MESSAGES, self::MAILBOX_HAS_NO_MESSAGES)) {
                if ($this->schedulerStatus->cmpset(self::IDLE, self::RUNNING)) {
                    goto process;
                }
            }
        };
    }

    public function run(): void
    {
        try {
            while (true) {
                $msg = $this->systemMailbox->pop();
                if (!$msg->valueIsNull()) {
                    $this->handleSystemMessage($msg);
                    continue;
                }

                if ($this->suspended->get() === 1) {
                    return;
                }

                $msg = $this->userMailbox->popMany($this->batchSize);
                if (!$msg->valueIsNull()) {
                    $this->handleUserMessage($msg);
                } else {
                    return;
                }
            }
        } catch (Throwable $e) {
            // escalate failure
            // all exceptions are escalated to the supervisor
            $this->invoker?->escalateFailure($e, $msg ?? null);
        }
    }

    public function userMessageCount(): int
    {
        return $this->userMailbox->length();
    }

    public function registerHandlers(
        MessageInvokerInterface $invoker,
        DispatcherInterface $dispatcher
    ): void {
        $this->invoker = $invoker;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param mixed $msg
     * @return void
     */
    protected function handleSystemMessage(mixed $msg): void
    {
        if ($msg instanceof QueueResult) {
            $msg = $msg->value();
        }
        switch (true) {
            case $msg instanceof SuspendMailbox:
                $this->suspended->set(1);
                break;
            case $msg instanceof ResumeMailbox:
                $this->suspended->set(0);
                break;
            default:
                $this->invoker?->invokeSystemMessage($msg);
        }
    }

    /**
     * @param mixed $msg
     * @return void
     */
    protected function handleUserMessage(mixed $msg): void
    {
        if ($msg instanceof QueueResult) {
            $msg = $msg->value();
        }
        $this->invoker?->invokeUserMessage($msg);
    }
}
