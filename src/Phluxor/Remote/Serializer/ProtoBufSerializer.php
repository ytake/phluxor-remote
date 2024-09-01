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

use Exception;
use Google\Protobuf\Internal\Message;
use Phluxor\Remote\Exception\SerializerException;
use Phluxor\Remote\Message\JsonMessage;
use Phluxor\Remote\SerializerInterface;
use ReflectionClass;
use ReflectionException;

class ProtoBufSerializer implements SerializerInterface
{
    public function serialize(mixed $msg): SerializerResult
    {
        if ($msg instanceof Message) {
            return new SerializerResult($msg->serializeToString(), null);
        }
        return new SerializerResult(
            '',
            new SerializerException("message must be Google\\Protobuf\\Internal\\Message")
        );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function deserialize(string $typeName, string $bytes): Message|JsonMessage
    {
        $named = class_exists($typeName);
        if (!$named) {
            throw new SerializerException(
                sprintf('Unknown message type: %s', $typeName)
            );
        }
        $ref = new ReflectionClass($typeName);
        $obj = $ref->newInstance();
        if (!$obj instanceof Message) {
            throw new SerializerException(
                sprintf('Unknown message type: %s', $typeName)
            );
        }
        $obj->mergeFromString($bytes);
        return $obj;
    }

    public function getTypeName(mixed $msg): TypeNameResult
    {
        if ($msg instanceof Message) {
            return new TypeNameResult(get_debug_type($msg), true);
        }
        return new TypeNameResult('', false);
    }
}
