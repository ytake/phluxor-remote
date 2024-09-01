<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: message.proto

namespace Phluxor\Remote\ProtoBuf;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>remote.MessageHeader</code>
 */
class MessageHeader extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>map<string, string> header_data = 1;</code>
     */
    private $header_data;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type array|\Google\Protobuf\Internal\MapField $header_data
     * }
     */
    public function __construct($data = NULL) {
        \Phluxor\Remote\Metadata\ProtoBuf\Message::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>map<string, string> header_data = 1;</code>
     * @return \Google\Protobuf\Internal\MapField
     */
    public function getHeaderData()
    {
        return $this->header_data;
    }

    /**
     * Generated from protobuf field <code>map<string, string> header_data = 1;</code>
     * @param array|\Google\Protobuf\Internal\MapField $var
     * @return $this
     */
    public function setHeaderData($var)
    {
        $arr = GPBUtil::checkMapField($var, \Google\Protobuf\Internal\GPBType::STRING, \Google\Protobuf\Internal\GPBType::STRING);
        $this->header_data = $arr;

        return $this;
    }

}

