<?php

declare(strict_types=1);

namespace Patterns\Tests;

use PHPUnit\Framework\TestCase;
use Patterns\EventChannel;
use Patterns\EventProvider;
use Patterns\Tests\Fakes\FakeProvider;
use Patterns\Tests\Fakes\InMemoryEventChannel;
use Patterns\Tests\Fakes\TestEvent;
use ReflectionClass;
use ReflectionMethod;

/**
 * The provider port - the "common shape" every transport adapter implements.
 *
 * One method. What makes that worth a test is what is *not* in it: no registry to
 * introspect, no removal, no disconnect, and above all no options bag. A provider
 * is constructed with its configuration (endpoint, credentials, pool size) exactly
 * as `S3` is, and then asked for channels. The moment a config bag enters the
 * port, the pattern is shipping policy.
 */
final class EventProviderTest extends TestCase
{
    public function testProviderDeclaresExactlyOneMethod(): void
    {
        $own = [];

        foreach ((new ReflectionClass(EventProvider::class))->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === EventProvider::class) {
                $own[] = $method->getName();
            }
        }

        sort($own);

        $this->assertSame(['channel'], $own);
    }

    public function testChannelTakesATopicAndReturnsAChannel(): void
    {
        $channel = new ReflectionMethod(EventProvider::class, 'channel');

        $this->assertSame('Patterns\\EventChannel', (string) $channel->getReturnType());
        $this->assertSame(1, $channel->getNumberOfParameters());
        $this->assertSame('string', (string) $channel->getParameters()[0]->getType());
    }

    public function testProviderIsSatisfiableWithOneMethodAndNoConfiguration(): void
    {
        $provider = new FakeProvider();

        $this->assertInstanceOf(EventProvider::class, $provider);
        $this->assertInstanceOf(EventChannel::class, $provider->channel('stock.updated'));
    }

    public function testTheSameTopicReturnsAUsableChannel(): void
    {
        $provider = new FakeProvider();

        $first = $provider->channel('stock.updated');
        $second = $provider->channel('stock.updated');

        $this->assertSame($first, $second, 'a provider must not hand out a different channel for the same topic');

        $received = [];

        $first->connect();
        $first->on('stock.updated', function () use (&$received): void {
            $received[] = true;
        });

        $second->publish(new TestEvent('stock.updated'));

        $this->assertCount(1, $received, 'the two handles are the same channel');
    }

    public function testAProviderCanBeGivenTheChannelItHandsOut(): void
    {
        $injected = new InMemoryEventChannel();

        $provider = new FakeProvider($injected);

        $this->assertSame($injected, $provider->channel('anything'));
        $this->assertSame($injected, $provider->channel('something-else'), 'one injected channel is reused');
    }

    public function testThePortCarriesNoRegistryIntrospection(): void
    {
        // topics() exists on the *fake*, because a caller may keep its own list.
        // On the port it would be state leaking into a contract - the same call
        // that removed getState()/getId() from the channel.
        $this->assertTrue(method_exists(FakeProvider::class, 'topics'));
        $this->assertFalse(method_exists(EventProvider::class, 'getChannels'));
        $this->assertFalse(method_exists(EventProvider::class, 'removeChannel'));
        $this->assertFalse(method_exists(EventProvider::class, 'disconnect'));
    }
}
