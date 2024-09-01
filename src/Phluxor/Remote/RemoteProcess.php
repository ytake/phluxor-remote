<?php

declare(strict_types=1);

namespace Phluxor\Remote;

use Phluxor\ActorSystem\Message\MessageEnvelope;
use Phluxor\ActorSystem\ProcessInterface;
use Phluxor\ActorSystem\ProtoBuf\Stop;
use Phluxor\ActorSystem\Ref;

class RemoteProcess implements ProcessInterface
{
    public function __construct(
        private Ref $ref,
        private Remote $remote
    ) {
    }

    public function sendUserMessage(?Ref $pid, mixed $message): void
    {
        $unwrap = MessageEnvelope::unwrapEnvelope($message);
        $this->remote->sendMessage(
            $pid,
            $unwrap['header'],
            $unwrap['message'],
            $unwrap['sender'],
            0
        );
    }

    public function sendSystemMessage(Ref $pid, mixed $message): void
    {
        // TODO: Implement sendSystemMessage() method.
    }

    public function stop(Ref $pid): void
    {
        $this->sendSystemMessage($pid, new Stop());
    }
}
