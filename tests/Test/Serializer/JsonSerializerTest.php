<?php

declare(strict_types=1);

namespace Test\Serializer;

use Phluxor\ActorSystem\ProtoBuf\Pid;
use Phluxor\Remote\Exception\SerializerException;
use Phluxor\Remote\Message\JsonMessage;
use Phluxor\Remote\Serializer\JsonSerializer;
use PHPUnit\Framework\TestCase;

class JsonSerializerTest extends TestCase
{
    public function testShouldReturnSerializerException(): void
    {
        $serializer = new JsonSerializer();
        $message = new TestType();
        $serialized = $serializer->serialize($message);
        $this->assertInstanceOf(SerializerException::class, $serialized->exception);
    }

    /**
     * @throws \Throwable
     */
    public function testShouldBeAbleToSerializeJsonMessage(): void
    {
        $serializer = new JsonSerializer();
        $message = new JsonMessage('Test', (string) json_encode([1, 2, 3]));
        $serialized = $serializer->serialize($message);
        $this->assertNull($serialized->exception);
        $deserialized = $serializer->deserialize($message->typeName, $serialized->serialized);
        $this->assertInstanceOf(JsonMessage::class, $deserialized);
        $this->assertSame('Test', $deserialized->typeName);
        $this->assertSame([1, 2, 3], json_decode($deserialized->json));
    }

    /**
     * @throws \Throwable
     */
    public function testShouldReturnProtoBufMessage(): void
    {
        $serializer = new JsonSerializer();
        $json = new JsonMessage(Pid::class, (string) json_encode(['id' => 'test']));
        $serialized = $serializer->serialize($json);
        $deserialized = $serializer->deserialize(Pid::class, $serialized->serialized);
        $this->assertInstanceOf(Pid::class, $deserialized);
        $this->assertSame("test", $deserialized->getId());
    }
}
