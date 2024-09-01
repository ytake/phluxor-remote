<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: message.proto

namespace Phluxor\Remote\ProtoBuf;

use UnexpectedValueException;

/**
 * Protobuf type <code>remote.ListProcessesMatchType</code>
 */
class ListProcessesMatchType
{
    /**
     * Generated from protobuf enum <code>MatchPartOfString = 0;</code>
     */
    const MatchPartOfString = 0;
    /**
     * Generated from protobuf enum <code>MatchExactString = 1;</code>
     */
    const MatchExactString = 1;
    /**
     * Generated from protobuf enum <code>MatchRegex = 2;</code>
     */
    const MatchRegex = 2;

    private static $valueToName = [
        self::MatchPartOfString => 'MatchPartOfString',
        self::MatchExactString => 'MatchExactString',
        self::MatchRegex => 'MatchRegex',
    ];

    public static function name($value)
    {
        if (!isset(self::$valueToName[$value])) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no name defined for value %s', __CLASS__, $value));
        }
        return self::$valueToName[$value];
    }


    public static function value($name)
    {
        $const = __CLASS__ . '::' . strtoupper($name);
        if (!defined($const)) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no value defined for name %s', __CLASS__, $name));
        }
        return constant($const);
    }
}

