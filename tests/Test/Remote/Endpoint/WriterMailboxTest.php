<?php

declare(strict_types=1);

namespace Test\Remote\Endpoint;

use Phluxor\ActorSystem\Dispatcher\CoroutineDispatcher;
use Phluxor\Remote\Endpoint\EndpointWriterMailbox;
use PHPUnit\Framework\TestCase;
use Phluxor\Buffer\Queue as RingBufferQueue;
use Phluxor\Mspc\Queue as MpscQueue;
use Swoole\Coroutine\WaitGroup;

use function Swoole\Coroutine\run;

class WriterMailboxTest extends TestCase
{
    public function testWriterMailboxUserMessageWhenBatchSizeIs1000(): void
    {
        run(function () {
            go(function () {
                $wg = new WaitGroup();
                $wg->add();
                $mailbox = new EndpointWriterMailbox(
                    1000,
                    new RingBufferQueue(1000000),
                    new MpscQueue()
                );
                $invoker = new StubInvoker();
                $invoker->withUserMessageReceiveHandler(function (mixed $message) use (&$counter, &$wg) {
                    $this->assertIsArray($message);
                    $counter++;
                    if (count($message) == 1000) {
                        $wg->done();
                    }
                });
                $mailbox->registerHandlers(
                    $invoker,
                    new CoroutineDispatcher(300)
                );
                for ($i = 0; $i < 1000; $i++) {
                    $mailbox->postUserMessage($i);
                }
                $wg->wait();
                $this->assertSame(1, $counter);
            });
        });
    }

    // このテストは1000回のsystem messageを送信し、全てのsystem messageが処理されたことを確認する
    public function testShouldHandleSystemMessageOneByOne(): void
    {
        run(function () {
            go(function () {
                $wg = new WaitGroup();
                $wg->add();
                $mailbox = new EndpointWriterMailbox(
                    1000,
                    new RingBufferQueue(1000000),
                    new MpscQueue()
                );
                $invoker = new StubInvoker();
                $invoker->withSystemMessageReceiveHandler(function (mixed $message) use (&$counter, &$wg) {
                    $this->assertIsInt($message);
                    $counter++;
                    if ($message == 999) {
                        $wg->done();
                    }
                });
                $mailbox->registerHandlers(
                    $invoker,
                    new CoroutineDispatcher(300)
                );
                for ($i = 0; $i < 1000; $i++) {
                    $mailbox->postSystemMessage($i);
                }
                $wg->wait();
                $this->assertSame(1000, $counter);
            });
        });
    }
}
