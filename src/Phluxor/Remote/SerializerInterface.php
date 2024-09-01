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

namespace Phluxor\Remote;

use Google\Protobuf\Internal\Message;
use Phluxor\Remote\Message\JsonMessage;
use Phluxor\Remote\Serializer\SerializerResult;
use Phluxor\Remote\Serializer\TypeNameResult;

interface SerializerInterface
{
    public function serialize(mixed $msg): SerializerResult;

    /**
     * @param string $typeName
     * @param string $bytes
     * @return Message|JsonMessage
     */
    public function deserialize(string $typeName, string $bytes): Message|JsonMessage;

    /**
     * @param mixed $msg
     * @return TypeNameResult
     */
    public function getTypeName(mixed $msg): TypeNameResult;
}
