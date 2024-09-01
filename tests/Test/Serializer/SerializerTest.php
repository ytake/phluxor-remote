<?php

declare(strict_types=1);

namespace Test\Serializer;

use Phluxor\ActorSystem;
use Phluxor\Remote\Message\JsonMessage;
use Phluxor\Remote\Serializer\SerializerManager;
use PHPUnit\Framework\TestCase;

use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;

class SerializerTest extends TestCase
{
    public function testProtobufSerializerSerializePID(): void
    {
        run(function () {
            go(function () {
                $system = ActorSystem::create();
                $ref = $system->newLocalAddress("test");
                $sm = new SerializerManager();
                $serialized = $sm->serialize($ref->protobufPid(), 0);
                $res = $sm->deserialize($serialized->serialized, $serialized->typeNameResult->name, 0);
                $this->assertInstanceOf(ActorSystem\ProtoBuf\Pid::class, $res->message);
                $this->assertSame("test", $res->message->getId());
            });
        });
    }

    public function testCanSerializeAndDeserializeJson(): void
    {
        $json = new JsonMessage("Test", (string) json_encode([10]));
        $sm = new SerializerManager();
        $serialized = $sm->serialize($json, 1);
        $res = $sm->deserialize($serialized->serialized, $serialized->typeNameResult->name, 1);
        $this->assertInstanceOf(JsonMessage::class, $res->message);
        $this->assertSame("Test", $res->message->typeName);
        $this->assertSame([10], json_decode($res->message->json));
    }

    public function testCanSerializeAndDeserializeProtoBuf(): void
    {
        $json = new JsonMessage(ActorSystem\ProtoBuf\Pid::class, (string) json_encode(['id' => 'test']));
        $sm = new SerializerManager();
        $serialized = $sm->serialize($json, 1);
        $res = $sm->deserialize($serialized->serialized, $serialized->typeNameResult->name, 1);
        $this->assertInstanceOf(ActorSystem\ProtoBuf\Pid::class, $res->message);
        $this->assertSame("test", $res->message->getId());
    }
}
