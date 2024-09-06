<?php

declare(strict_types=1);

namespace Phluxor\Remote;

use Phluxor\ActorSystem\Message\MessageEnvelope;
use Phluxor\ActorSystem\ProcessInterface;
use Phluxor\ActorSystem\ProtoBuf\Stop;
use Phluxor\ActorSystem\ProtoBuf\Unwatch;
use Phluxor\ActorSystem\ProtoBuf\Watch;
use Phluxor\ActorSystem\Ref;
use Phluxor\Remote\Message\RemoteUnwatch;
use Phluxor\Remote\Message\RemoteWatch;

readonly class RemoteProcess implements ProcessInterface
{
    public function __construct(
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
            -1
        );
    }

    public function sendSystemMessage(Ref $pid, mixed $message): void
    {
        switch (true) {
            case $message instanceof Watch:
                if ($message->getWatcher() === null) {
                    return;
                }
                $rw = new RemoteWatch(
                    watcher: $message->getWatcher(),
                    watchee: $pid->protobufPid(),
                );
                $this->remote->getEndpointManager()->remoteWatch($rw);
                break;
            case $message instanceof Unwatch:
                if ($message->getWatcher() === null) {
                    return;
                }
                $ruw = new RemoteUnwatch(
                    watcher: $message->getWatcher(),
                    watchee: $pid->protobufPid(),
                );
                $this->remote->getEndpointManager()->remoteUnwatch($ruw);
                break;
            default:
                $this->remote->sendMessage(
                    $pid,
                    null,
                    $message,
                    null,
                    -1
                );
        }
    }

    public function stop(Ref $pid): void
    {
        $this->sendSystemMessage($pid, new Stop());
    }
}
