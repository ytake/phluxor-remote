# Phluxor Remote

## About

Phluxor Remoteは、Phluxorアクターシステムを活用したリモートアクターシステムを作成するためのライブラリです。  

Phluxor Remote is a library that allows you to create remote actor systems in Phluxor.

It is inspired by the [Proto.Remote](https://proto.actor/docs/remote/).

with Phluxor Remote you can create a remote actor system that can communicate with other actor systems over network.

## Usage

Phluxor Remote uses [Swoole](https://www.swoole.com/).  

Between the two nodes, use the `ProtoBuf` serialization format.  
Websocket is used as the transport layer.  

### Node1

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Phluxor\ActorSystem;
use Phluxor\Remote\Config;
use Phluxor\Remote\Kind;
use Phluxor\Remote\Remote;
use Test\ProtoBuf\HelloRequest;
use Test\ProtoBuf\HelloResponse;

\Swoole\Coroutine\run(function () {
    \Swoole\Coroutine\go(function () {
        $system = ActorSystem::create();
        $config = new Config('localhost', 50053, Config::withUseWebSocket(true));
        $remote = new Remote($system, $config);
        $remote->start();
        $props = ActorSystem\Props::fromFunction(
            new ActorSystem\Message\ReceiveFunction(
                function (ActorSystem\Context\ContextInterface $context) {
                    $message = $context->message();
                    if ($message instanceof HelloRequest) {
                        $context->respond(new HelloResponse([
                            'Message' => 'Hello from remote node',
                        ]));
                    }
                }
            )
        );
        $system->root()->spawnNamed($props, 'hello');
    });
});
```

### Node2

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Phluxor\ActorSystem;
use Phluxor\Remote\Config;
use Phluxor\Remote\Kind;
use Phluxor\Remote\Remote;
use Test\ProtoBuf\HelloRequest;
use Test\ProtoBuf\HelloResponse;

\Swoole\Coroutine\run(function () {
    \Swoole\Coroutine\go(function () {
        $system = ActorSystem::create();
        $config = new Config('localhost', 50052, Config::withUseWebSocket(true));
        $remote = new Remote($system, $config);
        $remote->start();
        $future = $system->root()->requestFuture(
            new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                'address' => 'localhost:50053',
                'id' => 'hello',
            ])),
            new HelloRequest(),
            1
        );
        $r = $future->result()->value();
        $r->getMessage(); // Hello from remote node! 
    });
});
```
