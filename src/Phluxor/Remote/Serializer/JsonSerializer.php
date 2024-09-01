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

use Google\Protobuf\Internal\Message;
use Phluxor\Remote\Exception\SerializerException;
use Phluxor\Remote\Message\JsonMessage;
use Phluxor\Remote\SerializerInterface;
use ReflectionClass;
use Throwable;

use function json_encode;

class JsonSerializer implements SerializerInterface
{
    public function serialize(mixed $msg): SerializerResult
    {
        if ($msg instanceof JsonMessage) {
            return new SerializerResult($msg->json, null);
        } elseif ($msg instanceof Message) {
            $json = json_encode($msg->serializeToJsonString());
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new SerializerResult(
                    '',
                    new SerializerException(json_last_error_msg())
                );
            }
            if ($json === false) {
                return new SerializerResult(
                    '',
                    new SerializerException('Failed to encode message')
                );
            }
            return new SerializerResult($json, null);
        }
        return new SerializerResult(
            '',
            new SerializerException("message must be Google\\Protobuf\\Internal\\Message")
        );
    }

    /**
     * @throws Throwable
     */
    public function deserialize(string $typeName, string $bytes): Message|JsonMessage
    {
        $className = class_exists($typeName);
        if (!$className) {
            return new JsonMessage($typeName, $bytes);
        }
        $ref = new ReflectionClass($typeName);
        $obj = $ref->newInstance();
        if (!$obj instanceof Message) {
            throw new SerializerException(
                sprintf('Unknown message type: %s', $typeName)
            );
        }
        /** @var $obj Message */
        $obj->mergeFromJsonString($bytes);
        return $obj;
    }

    public function getTypeName(mixed $msg): TypeNameResult
    {
        if ($msg instanceof JsonMessage) {
            return new TypeNameResult($msg->typeName, true);
        } elseif ($msg instanceof Message) {
            return new TypeNameResult(get_debug_type($msg), true);
        }
        return new TypeNameResult('', false);
    }
}
