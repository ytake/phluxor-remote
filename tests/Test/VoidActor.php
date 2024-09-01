<?php

declare(strict_types=1);

namespace Test;

use Phluxor\ActorSystem\Context\ContextInterface;
use Phluxor\ActorSystem\Message\ActorInterface;

class VoidActor implements ActorInterface
{
    public function receive(ContextInterface $context): void
    {
        // TODO: Implement receive() method.
    }
}
