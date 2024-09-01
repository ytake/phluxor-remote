<?php

declare(strict_types=1);

namespace Test;

use Phluxor\ActorSystem\Props;
use Phluxor\Remote\Config;
use Phluxor\Remote\Kind;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testShouldReturnDefaultConfig(): void
    {
        $config = new Config('localhost', 50052);
        $this->assertSame('localhost:50052', $config->address());
        $this->assertSame('', $config->getAdvertisedHost());
        $this->assertFalse($config->isSsl());
        $this->assertSame(5, $config->getMaxRetryCount());
        $this->assertEmpty($config->getKinds());
        $this->assertSame(1000, $config->getEndpointWriterBatchSize());
        $this->assertSame(1000000, $config->getEndpointWriterQueueSize());
        $this->assertSame(1000, $config->getEndpointManagerBatchSize());
        $this->assertSame(1000000, $config->getEndpointManagerQueueSize());
    }

    public function testShouldReturnConfigWithAdvertisedHost(): void
    {
        $config = new Config('localhost', 50052, Config::withAdvertisedHost('Hi'));
        $this->assertSame('Hi', $config->getAdvertisedHost());
    }

    public function testShouldReturnConfigWithSsl(): void
    {
        $config = new Config('localhost', 50052, Config::withSsl(true));
        $this->assertTrue($config->isSsl());
    }

    public function testShouldReturnConfigWithMaxRetryCount(): void
    {
        $config = new Config('localhost', 50052, Config::withMaxRetryCount(10));
        $this->assertSame(10, $config->getMaxRetryCount());
    }

    public function testShouldReturnConfigWithKinds(): void
    {
        $config = new Config(
            'localhost',
            50052,
            Config::withKinds(
                new Kind('someKind', Props::fromProducer(fn() => new VoidActor())),
                new Kind('someOther', Props::fromProducer(fn() => new VoidActor()))
            )
        );
        $kinds = $config->getKinds();
        $this->assertCount(2, $kinds);
        $this->assertArrayHasKey('someKind', $kinds);
        $this->assertArrayHasKey('someOther', $kinds);
    }

    public function testShouldReturnConfigWithEndpointWriterBatchSize(): void
    {
        $config = new Config('localhost', 50052, Config::withEndpointWriterBatchSize(2000));
        $this->assertSame(2000, $config->getEndpointWriterBatchSize());
    }

    public function testShouldReturnConfigWithEndpointWriterQueueSize(): void
    {
        $config = new Config('localhost', 50052, Config::withEndpointWriterQueueSize(2000000));
        $this->assertSame(2000000, $config->getEndpointWriterQueueSize());
    }

    public function testShouldReturnConfigWithEndpointManagerBatchSize(): void
    {
        $config = new Config('localhost', 50052, Config::withEndpointManagerBatchSize(2000));
        $this->assertSame(2000, $config->getEndpointManagerBatchSize());
    }

    public function testShouldReturnConfigWithEndpointManagerQueueSize(): void
    {
        $config = new Config('localhost', 50052, Config::withEndpointManagerQueueSize(2000000));
        $this->assertSame(2000000, $config->getEndpointManagerQueueSize());
    }
}
