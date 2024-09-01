<?php

declare(strict_types=1);

namespace Test\Remote\Endpoint;

use Closure;
use Phluxor\ActorSystem\Mailbox\MessageInvokerInterface;

class StubInvoker implements MessageInvokerInterface
{
    private Closure|null $handler;
    private Closure|null $systemHandler;

    public function invokeSystemMessage(mixed $message): void
    {
        if ($this->systemHandler !== null) {
            $handler = $this->systemHandler;
            $handler($message);
        }
    }

    public function invokeUserMessage(mixed $message): void
    {
        if ($this->handler !== null) {
            $handler = $this->handler;
            $handler($message);
        }
    }

    public function escalateFailure(mixed $reason, mixed $message): void
    {
    }

    public function withUserMessageReceiveHandler(Closure $handler): MessageInvokerInterface
    {
        $this->handler = $handler;
        return $this;
    }

    public function withSystemMessageReceiveHandler(Closure $handler): MessageInvokerInterface
    {
        $this->systemHandler = $handler;
        return $this;
    }
}
