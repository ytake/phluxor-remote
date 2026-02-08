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

namespace Phluxor\Remote\Serializer;

use Phluxor\Remote\SerializerInterface;
use Throwable;

class SerializerManager
{
    /** @var SerializerInterface[] */
    private array $serializers = [];

    public function __construct()
    {
        $this->registerSerializer(new ProtoBufSerializer());
        $this->registerSerializer(new JsonSerializer());
    }

    public function registerSerializer(
        SerializerInterface $serializer
    ): void {
        $this->serializers[] = $serializer;
    }

    /**
     * @param mixed $message
     * @param int $serializerID
     * @return Serialized
     */
    public function serialize(
        mixed $message,
        int $serializerID
    ): Serialized {
        try {
            $res = $this->serializers[$serializerID]->serialize($message);
            $typeName = $this->serializers[$serializerID]->getTypeName($message);
            return new Serialized($res->serialized, $typeName, $res->exception);
        } catch (Throwable $e) {
            return new Serialized('', new TypeNameResult('', false), $e);
        }
    }

    /**
     * @param string $message
     * @param string $typeName
     * @param int $serializerID
     * @return Deserialized
     */
    public function deserialize(string $message, string $typeName, int $serializerID): Deserialized
    {
        try {
            return new Deserialized(
                $this->serializers[$serializerID]->deserialize($typeName, $message),
                null
            );
        } catch (Throwable $e) {
            return new Deserialized(null, $e);
        }
    }
}
