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

class ResponseStatusCode
{
    public const int OK = 0;
    public const int UNAVAILABLE = 1;
    public const int TIMEOUT = 2;
    public const int PROCESS_NAME_ALREADY_EXIST = 3;
    public const int ERROR = 4;
    public const int DEAD_LETTER = 5;
    public const int MAX = 6; // just a boundary

    /** @var array<int, string> */
    private static array $responseNames = [
        self::OK => 'ResponseStatusCodeOK',
        self::UNAVAILABLE => 'ResponseStatusCodeUNAVAILABLE',
        self::TIMEOUT => 'ResponseStatusCodeTIMEOUT',
        self::PROCESS_NAME_ALREADY_EXIST => 'ResponseStatusCodePROCESSNAMEALREADYEXIST',
        self::ERROR => 'ResponseStatusCodeERROR',
        self::DEAD_LETTER => 'ResponseStatusCodeDeadLetter',
    ];

    public function __construct(
        private readonly int $value
    ) {
    }

    public function toInt32(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        if ($this->value < 0 || $this->value >= self::MAX) {
            return 'ResponseStatusCode-' . $this->value;
        }
        return self::$responseNames[$this->value];
    }

    public function asError(): ?ResponseError
    {
        return match ($this->value) {
            self::OK => null,
            self::UNAVAILABLE => new ResponseError('ErrUnAvailable'),
            self::TIMEOUT => new ResponseError('ErrTimeout'),
            self::PROCESS_NAME_ALREADY_EXIST => new ResponseError('ErrProcessNameAlreadyExist'),
            self::ERROR => new ResponseError('ErrUnknownError'),
            self::DEAD_LETTER => new ResponseError('ErrDeadLetter'),
            default => new ResponseError('Unknown error code: ' . $this->value),
        };
    }
}
