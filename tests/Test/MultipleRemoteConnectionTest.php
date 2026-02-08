<?php

declare(strict_types=1);

namespace Test;

use Phluxor\ActorSystem;
use Phluxor\Remote\Config;
use Phluxor\Remote\Remote;
use PHPUnit\Framework\TestCase;
use Test\ProtoBuf\HelloRequest;

use function Swoole\Coroutine\run;

class MultipleRemoteConnectionTest extends TestCase
{
    /**
     * 3台のリモートサーバに対して独立してメッセージ送信できることを検証する。
     * send()（fire-and-forget）を使い、サーバ側で受信を確認する。
     */
    public function testSendToMultipleRemoteServers(): void
    {
        run(function () {
            $receivedA = [];
            $receivedB = [];
            $receivedC = [];
            \Swoole\Coroutine\go(function () use (&$receivedA) {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50060, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                $system->root()->spawnNamed(
                    $this->recordingActorProps($receivedA),
                    'hello'
                );
                \Swoole\Coroutine::sleep(3);
                $remote->shutdown();
                $this->assertCount(2, $receivedA);
            });
            \Swoole\Coroutine\go(function () use (&$receivedB) {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50061, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                $system->root()->spawnNamed(
                    $this->recordingActorProps($receivedB),
                    'hello'
                );
                \Swoole\Coroutine::sleep(3);
                $remote->shutdown();
                $this->assertCount(2, $receivedB);
            });
            \Swoole\Coroutine\go(function () use (&$receivedC) {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50062, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                $system->root()->spawnNamed(
                    $this->recordingActorProps($receivedC),
                    'hello'
                );
                \Swoole\Coroutine::sleep(3);
                $remote->shutdown();
                $this->assertCount(2, $receivedC);
            });
            \Swoole\Coroutine\go(function () {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50063, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                \Swoole\Coroutine::sleep(0.3);
                for ($i = 0; $i < 2; $i++) {
                    $system->root()->send(
                        new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                            'address' => 'localhost:50060',
                            'id' => 'hello',
                        ])),
                        new HelloRequest()
                    );
                    $system->root()->send(
                        new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                            'address' => 'localhost:50061',
                            'id' => 'hello',
                        ])),
                        new HelloRequest()
                    );
                    $system->root()->send(
                        new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                            'address' => 'localhost:50062',
                            'id' => 'hello',
                        ])),
                        new HelloRequest()
                    );
                }
                \Swoole\Coroutine::sleep(2);
                $remote->shutdown();
            });
        });
    }

    /**
     * 1台のサーバが切断されても、他のサーバとの通信に影響しないことを検証する。
     * Phase1: 全3台に送信して受信確認
     * Phase2: サーバBがシャットダウン
     * Phase3: サーバA,Cに再度送信して受信確認
     */
    public function testOneServerDisconnectDoesNotAffectOthers(): void
    {
        run(function () {
            $receivedA = [];
            $receivedC = [];
            \Swoole\Coroutine\go(function () use (&$receivedA) {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50070, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                $system->root()->spawnNamed(
                    $this->recordingActorProps($receivedA),
                    'hello'
                );
                \Swoole\Coroutine::sleep(6);
                $remote->shutdown();
                $this->assertCount(2, $receivedA);
            });
            \Swoole\Coroutine\go(function () {
                $dummy = [];
                $system = ActorSystem::create();
                $config = new Config('localhost', 50071, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                $system->root()->spawnNamed(
                    $this->recordingActorProps($dummy),
                    'hello'
                );
                \Swoole\Coroutine::sleep(2);
                $remote->shutdown(true);
            });
            \Swoole\Coroutine\go(function () use (&$receivedC) {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50072, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                $system->root()->spawnNamed(
                    $this->recordingActorProps($receivedC),
                    'hello'
                );
                \Swoole\Coroutine::sleep(6);
                $remote->shutdown();
                $this->assertCount(2, $receivedC);
            });
            \Swoole\Coroutine\go(function () {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50073, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                \Swoole\Coroutine::sleep(0.3);
                // Phase 1: 全3台に送信
                $system->root()->send(
                    new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                        'address' => 'localhost:50070',
                        'id' => 'hello',
                    ])),
                    new HelloRequest()
                );
                $system->root()->send(
                    new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                        'address' => 'localhost:50071',
                        'id' => 'hello',
                    ])),
                    new HelloRequest()
                );
                $system->root()->send(
                    new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                        'address' => 'localhost:50072',
                        'id' => 'hello',
                    ])),
                    new HelloRequest()
                );
                // Phase 2: サーバB (50071) がシャットダウンするのを待つ
                \Swoole\Coroutine::sleep(3);
                // Phase 3: サーバA,Cに再度送信（Bは切断済み）
                $system->root()->send(
                    new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                        'address' => 'localhost:50070',
                        'id' => 'hello',
                    ])),
                    new HelloRequest()
                );
                $system->root()->send(
                    new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                        'address' => 'localhost:50072',
                        'id' => 'hello',
                    ])),
                    new HelloRequest()
                );
                \Swoole\Coroutine::sleep(2);
                $remote->shutdown();
            });
        });
    }

    /**
     * サーバ切断後、クライアントが別サーバへ新しいエンドポイントを作成して通信できることを検証する。
     *
     * Phase1: サーバA (50080) にメッセージ送信→受信確認
     * Phase2: サーバA (50080) がgracefulシャットダウン開始
     * Phase3: サーバBへ初めて送信→新しいエンドポイント作成→受信確認
     *
     * シャットダウン順: クライアント(t=6)→サーバA(t=7, graceful完了)→サーバB(t=8)
     * Swoole\Coroutine\Http\Server はクライアントが切断されないとコネクションハンドラが
     * 残り続けるため、クライアントを先にシャットダウンする。
     */
    public function testNewEndpointAfterServerDisconnect(): void
    {
        run(function () {
            $receivedA = [];
            $receivedB = [];
            // サーバA (50080): graceful shutdown で接続を段階的にクリーンアップ
            \Swoole\Coroutine\go(function () use (&$receivedA) {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50080, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                $system->root()->spawnNamed(
                    $this->recordingActorProps($receivedA),
                    'hello'
                );
                // t=2: graceful shutdown 開始 (suspend→3秒待機→server.stop)
                // t=5: server.stop 完了。ただしコネクションハンドラが残る可能性あり
                // t=6: クライアントがシャットダウン→残ハンドラ終了→go()コルーチン終了
                \Swoole\Coroutine::sleep(2);
                $remote->shutdown(true);
                $this->assertCount(1, $receivedA);
            });
            // サーバB (50082): クライアント切断後にシャットダウン
            \Swoole\Coroutine\go(function () use (&$receivedB) {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50082, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                $system->root()->spawnNamed(
                    $this->recordingActorProps($receivedB),
                    'hello'
                );
                \Swoole\Coroutine::sleep(8);
                $remote->shutdown();
                $this->assertCount(1, $receivedB);
            });
            // クライアント (50081)
            \Swoole\Coroutine\go(function () {
                $system = ActorSystem::create();
                $config = new Config('localhost', 50081, Config::withUseWebSocket(true));
                $remote = new Remote($system, $config);
                $remote->start();
                $targetA = new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                    'address' => 'localhost:50080',
                    'id' => 'hello',
                ]));
                $targetB = new ActorSystem\Ref(new ActorSystem\ProtoBuf\Pid([
                    'address' => 'localhost:50082',
                    'id' => 'hello',
                ]));
                // Phase 1 (t=0.5): サーバAにのみ送信（サーバBへのエンドポイントはまだない）
                \Swoole\Coroutine::sleep(0.5);
                $system->root()->send($targetA, new HelloRequest());
                // Phase 2 (t=4): サーバAのgraceful shutdownが進行中。
                //   suspend→stream close→Receive()終了→接続が切れつつある
                // Phase 3 (t=4): サーバBへ初めて送信→新しいEndpointWriterが作成される
                \Swoole\Coroutine::sleep(3.5);
                $system->root()->send($targetB, new HelloRequest());
                // 受信待ちの後、クライアントをシャットダウン
                \Swoole\Coroutine::sleep(2);
                $remote->shutdown();
            });
        });
    }

    /**
     * @param list<string> $received
     */
    private function recordingActorProps(array &$received): ActorSystem\Props
    {
        return ActorSystem\Props::fromFunction(
            new ActorSystem\Message\ReceiveFunction(
                function (ActorSystem\Context\ContextInterface $context) use (&$received) {
                    if ($context->message() instanceof HelloRequest) {
                        $received[] = $context->self()?->protobufPid()?->getId();
                    }
                }
            )
        );
    }
}
