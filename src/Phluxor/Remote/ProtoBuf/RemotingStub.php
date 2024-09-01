<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Phluxor\Remote\ProtoBuf;

/**
 */
class RemotingStub {

    /**
     * @param \Grpc\ServerCallReader $reader read client request data of \Phluxor\Remote\ProtoBuf\RemoteMessage
     * @param \Grpc\ServerCallWriter $writer write response data of \Phluxor\Remote\ProtoBuf\RemoteMessage
     * @param \Grpc\ServerContext $context server request context
     * @return void
     */
    public function Receive(
        \Grpc\ServerCallReader $reader,
        \Grpc\ServerCallWriter $writer,
        \Grpc\ServerContext $context
    ): void {
        $context->setStatus(\Grpc\Status::unimplemented());
        $writer->finish();
    }

    /**
     * @param \Phluxor\Remote\ProtoBuf\ListProcessesRequest $request client request
     * @param \Grpc\ServerContext $context server request context
     * @return \Phluxor\Remote\ProtoBuf\ListProcessesResponse for response data, null if if error occured
     *     initial metadata (if any) and status (if not ok) should be set to $context
     */
    public function ListProcesses(
        \Phluxor\Remote\ProtoBuf\ListProcessesRequest $request,
        \Grpc\ServerContext $context
    ): ?\Phluxor\Remote\ProtoBuf\ListProcessesResponse {
        $context->setStatus(\Grpc\Status::unimplemented());
        return null;
    }

    /**
     * @param \Phluxor\Remote\ProtoBuf\GetProcessDiagnosticsRequest $request client request
     * @param \Grpc\ServerContext $context server request context
     * @return \Phluxor\Remote\ProtoBuf\GetProcessDiagnosticsResponse for response data, null if if error occured
     *     initial metadata (if any) and status (if not ok) should be set to $context
     */
    public function GetProcessDiagnostics(
        \Phluxor\Remote\ProtoBuf\GetProcessDiagnosticsRequest $request,
        \Grpc\ServerContext $context
    ): ?\Phluxor\Remote\ProtoBuf\GetProcessDiagnosticsResponse {
        $context->setStatus(\Grpc\Status::unimplemented());
        return null;
    }

    /**
     * Get the method descriptors of the service for server registration
     *
     * @return array of \Grpc\MethodDescriptor for the service methods
     */
    public final function getMethodDescriptors(): array
    {
        return [
            '/remote.Remoting/Receive' => new \Grpc\MethodDescriptor(
                $this,
                'Receive',
                '\Phluxor\Remote\ProtoBuf\RemoteMessage',
                \Grpc\MethodDescriptor::BIDI_STREAMING_CALL
            ),
            '/remote.Remoting/ListProcesses' => new \Grpc\MethodDescriptor(
                $this,
                'ListProcesses',
                '\Phluxor\Remote\ProtoBuf\ListProcessesRequest',
                \Grpc\MethodDescriptor::UNARY_CALL
            ),
            '/remote.Remoting/GetProcessDiagnostics' => new \Grpc\MethodDescriptor(
                $this,
                'GetProcessDiagnostics',
                '\Phluxor\Remote\ProtoBuf\GetProcessDiagnosticsRequest',
                \Grpc\MethodDescriptor::UNARY_CALL
            ),
        ];
    }

}
