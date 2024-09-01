# Protocol Buffers Code Generator

This is a code generator for the [Protocol Buffers](https://developers.google.com/protocol-buffers) serialization format.  
It generates code in the target language of your choice from a `.proto` file.

## Usage

```bash
# project root
$ cd ../
# generate PHP code
$ protoc -I=./vendor/phluxor/phluxor/protobuf/ --proto_path=protobuf  --php_out=src --plugin=protoc-gen-grpc=bins/opt/grpc_php_plugin protobuf/remote.proto
$ protoc -I=./vendor/phluxor/phluxor/protobuf/ --proto_path=protobuf  --php_out=src protobuf/message.proto
$ protoc -I=./vendor/phluxor/phluxor/protobuf/ --proto_path=protobuf --php_out=src --phluxor-websocket_out=src --plugin=protoc-gen-websocket=protoc-gen-phluxor-websocket protobuf/websocket_remote.proto
```

## Notes

https://github.com/grpc/grpc/issues/31408

[Bazel MacOS](https://bazel.build/install/os-x?hl=en)
