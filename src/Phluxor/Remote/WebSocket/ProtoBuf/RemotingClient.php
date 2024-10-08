<?php

declare(strict_types=1);

# Generated by the protocol buffer compiler (for Phluxor). DO NOT EDIT!
# source: websocket_remote.proto

namespace Phluxor\Remote\WebSocket\ProtoBuf;

use Google\Protobuf\Internal\Message;
use Phluxor\Remote\ProtoBuf\GetProcessDiagnosticsRequest;
use Phluxor\Remote\ProtoBuf\GetProcessDiagnosticsResponse;
use Phluxor\Remote\ProtoBuf\ListProcessesRequest;
use Phluxor\Remote\ProtoBuf\ListProcessesResponse;
use Phluxor\Remote\ProtoBuf\RemoteMessage;
use Phluxor\WebSocket;

class RemotingClient extends WebSocket\BaseStub
{
    /**
     * @param RemoteMessage $request
     * @param array<string|int, mixed> $metadata
     * @return ?RemoteMessage
     *
     * @throws WebSocket\Exception\InvokeException|\Exception
     */
    public function Receive(RemoteMessage $request, array $metadata = []): ?RemoteMessage // @phpcs:ignore
    {
        return $this->serverRequest(
            '/remote.Remoting/Receive',
            $request,
            [RemoteMessage::class, 'decode'],
            $metadata
        );
    }

    /**
     * @param ListProcessesRequest $request
     * @param array<string|int, mixed> $metadata
     * @return ListProcessesResponse
     *
     * @throws WebSocket\Exception\InvokeException|\Exception
     */
    public function ListProcesses(
        ListProcessesRequest $request,
        array $metadata = []
    ): ListProcessesResponse // @phpcs:ignore
    {
        return $this->serverRequest(
            '/remote.Remoting/ListProcesses',
            $request,
            ['\Phluxor\Remote\WebSocket\ProtoBuf\ListProcessesResponse', 'decode'],
            $metadata
        );
    }

    /**
     * @param GetProcessDiagnosticsRequest $request
     * @param array<string|int, mixed> $metadata
     * @return GetProcessDiagnosticsResponse
     *
     * @throws WebSocket\Exception\InvokeException|\Exception
     */
    public function GetProcessDiagnostics(
        GetProcessDiagnosticsRequest $request,
        array $metadata = []
    ): GetProcessDiagnosticsResponse // @phpcs:ignore
    {
        return $this->serverRequest(
            '/remote.Remoting/GetProcessDiagnostics',
            $request,
            ['\Phluxor\Remote\WebSocket\ProtoBuf\GetProcessDiagnosticsResponse', 'decode'],
            $metadata
        );
    }
}
