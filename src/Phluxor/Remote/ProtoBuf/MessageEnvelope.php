<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# NO CHECKED-IN PROTOBUF GENCODE
# source: message.proto

namespace Phluxor\Remote\ProtoBuf;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>remote.MessageEnvelope</code>
 */
class MessageEnvelope extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>int32 type_id = 1;</code>
     */
    protected $type_id = 0;
    /**
     * Generated from protobuf field <code>bytes message_data = 2;</code>
     */
    protected $message_data = '';
    /**
     * Generated from protobuf field <code>int32 target = 3;</code>
     */
    protected $target = 0;
    /**
     * Generated from protobuf field <code>int32 sender = 4;</code>
     */
    protected $sender = 0;
    /**
     * Generated from protobuf field <code>int32 serializer_id = 5;</code>
     */
    protected $serializer_id = 0;
    /**
     * Generated from protobuf field <code>.remote.MessageHeader message_header = 6;</code>
     */
    protected $message_header = null;
    /**
     * Generated from protobuf field <code>uint32 target_request_id = 7;</code>
     */
    protected $target_request_id = 0;
    /**
     * Generated from protobuf field <code>uint32 sender_request_id = 8;</code>
     */
    protected $sender_request_id = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int $type_id
     *     @type string $message_data
     *     @type int $target
     *     @type int $sender
     *     @type int $serializer_id
     *     @type \Phluxor\Remote\ProtoBuf\MessageHeader $message_header
     *     @type int $target_request_id
     *     @type int $sender_request_id
     * }
     */
    public function __construct($data = NULL) {
        \Phluxor\Remote\Metadata\ProtoBuf\Message::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>int32 type_id = 1;</code>
     * @return int
     */
    public function getTypeId()
    {
        return $this->type_id;
    }

    /**
     * Generated from protobuf field <code>int32 type_id = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setTypeId($var)
    {
        GPBUtil::checkInt32($var);
        $this->type_id = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bytes message_data = 2;</code>
     * @return string
     */
    public function getMessageData()
    {
        return $this->message_data;
    }

    /**
     * Generated from protobuf field <code>bytes message_data = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setMessageData($var)
    {
        GPBUtil::checkString($var, False);
        $this->message_data = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 target = 3;</code>
     * @return int
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Generated from protobuf field <code>int32 target = 3;</code>
     * @param int $var
     * @return $this
     */
    public function setTarget($var)
    {
        GPBUtil::checkInt32($var);
        $this->target = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 sender = 4;</code>
     * @return int
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * Generated from protobuf field <code>int32 sender = 4;</code>
     * @param int $var
     * @return $this
     */
    public function setSender($var)
    {
        GPBUtil::checkInt32($var);
        $this->sender = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 serializer_id = 5;</code>
     * @return int
     */
    public function getSerializerId()
    {
        return $this->serializer_id;
    }

    /**
     * Generated from protobuf field <code>int32 serializer_id = 5;</code>
     * @param int $var
     * @return $this
     */
    public function setSerializerId($var)
    {
        GPBUtil::checkInt32($var);
        $this->serializer_id = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.remote.MessageHeader message_header = 6;</code>
     * @return \Phluxor\Remote\ProtoBuf\MessageHeader|null
     */
    public function getMessageHeader()
    {
        return $this->message_header;
    }

    public function hasMessageHeader()
    {
        return isset($this->message_header);
    }

    public function clearMessageHeader()
    {
        unset($this->message_header);
    }

    /**
     * Generated from protobuf field <code>.remote.MessageHeader message_header = 6;</code>
     * @param \Phluxor\Remote\ProtoBuf\MessageHeader $var
     * @return $this
     */
    public function setMessageHeader($var)
    {
        GPBUtil::checkMessage($var, \Phluxor\Remote\ProtoBuf\MessageHeader::class);
        $this->message_header = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint32 target_request_id = 7;</code>
     * @return int
     */
    public function getTargetRequestId()
    {
        return $this->target_request_id;
    }

    /**
     * Generated from protobuf field <code>uint32 target_request_id = 7;</code>
     * @param int $var
     * @return $this
     */
    public function setTargetRequestId($var)
    {
        GPBUtil::checkUint32($var);
        $this->target_request_id = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>uint32 sender_request_id = 8;</code>
     * @return int
     */
    public function getSenderRequestId()
    {
        return $this->sender_request_id;
    }

    /**
     * Generated from protobuf field <code>uint32 sender_request_id = 8;</code>
     * @param int $var
     * @return $this
     */
    public function setSenderRequestId($var)
    {
        GPBUtil::checkUint32($var);
        $this->sender_request_id = $var;

        return $this;
    }

}

