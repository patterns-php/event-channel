<?php

declare(strict_types=1);

namespace Patterns\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Patterns\Tests\Fakes\InMemoryEventChannel;
use Patterns\Tests\Fakes\TestEvent;

/**
 * The executable specification of the EventChannel contract.
 *
 * This package ships no implementation, so these tests are what the contract
 * actually *means*. Every adapter is expected to pass the same suite.
 */
final class EventChannelContractTest extends TestCase
{
    private InMemoryEventChannel $channel;

    protected function setUp(): void
    {
        $this->channel = new InMemoryEventChannel();
        $this->channel->connect();
    }

    // ---------------------------------------------------------------------
    // Routing
    // ---------------------------------------------------------------------

    public function testDeliversToAMatchingSubscription(): void
    {
        $received = [];

        $this->channel->on('stock.updated', function (object $event) use (&$received): void {
            $received[] = $event;
        });

        $this->channel->publish(new TestEvent('stock.updated'));

        $this->assertCount(1, $received);
    }

    public function testDoesNotDeliverToANonMatchingSubscription(): void
    {
        $received = [];

        $this->channel->on('stock.updated', function () use (&$received): void {
            $received[] = true;
        });

        $this->channel->publish(new TestEvent('price.changed'));

        $this->assertSame([], $received);
    }

    /**
     * The routing key decides, not the class. Two instances of the same event
     * class can route to completely different subscribers.
     */
    public function testRoutingFollowsTheTypeAndNotTheClass(): void
    {
        $a = 0;
        $b = 0;

        $this->channel->on('a', function () use (&$a): void {
            $a++;
        });
        $this->channel->on('b', function () use (&$b): void {
            $b++;
        });

        $this->channel->publish(new TestEvent('b'));
        $this->channel->publish(new TestEvent('b'));
        $this->channel->publish(new TestEvent('a'));

        $this->assertSame(1, $a);
        $this->assertSame(2, $b);
    }

    public function testWildcardReceivesEveryType(): void
    {
        $received = [];

        $this->channel->on('*', function (object $event) use (&$received): void {
            $received[] = $event;
        });

        $this->channel->publish(new TestEvent('a'));
        $this->channel->publish(new TestEvent('b.c'));

        $this->assertCount(2, $received);
    }

    public function testHandlerReceivesThePublishedInstance(): void
    {
        $event = new TestEvent('x');
        $received = null;

        $this->channel->on('x', function (object $e) use (&$received): void {
            $received = $e;
        });

        $this->channel->publish($event);

        $this->assertSame($event, $received);
    }

    public function testHandlersFireInRegistrationOrder(): void
    {
        $order = [];

        $this->channel->on('x', function () use (&$order): void {
            $order[] = 'first';
        });
        $this->channel->on('x', function () use (&$order): void {
            $order[] = 'second';
        });

        $this->channel->publish(new TestEvent('x'));

        $this->assertSame(['first', 'second'], $order);
    }

    // ---------------------------------------------------------------------
    // Unsubscribing
    // ---------------------------------------------------------------------

    public function testOnReturnsAnUnsubscribeHandle(): void
    {
        $this->assertIsCallable($this->channel->on('x', static function (): void {
        }));
    }

    public function testUnsubscribeStopsFurtherDelivery(): void
    {
        $count = 0;

        $off = $this->channel->on('x', function () use (&$count): void {
            $count++;
        });

        $this->channel->publish(new TestEvent('x'));
        $off();
        $this->channel->publish(new TestEvent('x'));

        $this->assertSame(1, $count);
    }

    public function testUnsubscribeIsIdempotent(): void
    {
        $off = $this->channel->on('x', static function (): void {
        });

        $off();
        $off();

        $this->expectNotToPerformAssertions();
    }

    /**
     * A handler must be able to remove itself while dispatching without
     * corrupting the dispatch in progress.
     */
    public function testHandlerMayUnsubscribeItself(): void
    {
        $calls = 0;

        $off = $this->channel->on('x', function () use (&$calls, &$off): void {
            $calls++;
            $off();
        });

        $this->channel->publish(new TestEvent('x'));
        $this->channel->publish(new TestEvent('x'));

        $this->assertSame(1, $calls);
    }

    // ---------------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------------

    public function testSubscribingBeforeConnectThrows(): void
    {
        $channel = new InMemoryEventChannel();

        $this->expectException(LogicException::class);

        $channel->on('x', static function (): void {
        });
    }

    public function testPublishingBeforeConnectThrows(): void
    {
        $channel = new InMemoryEventChannel();

        $this->expectException(LogicException::class);

        $channel->publish(new TestEvent('x'));
    }

    public function testPublishingAfterCloseThrows(): void
    {
        $this->channel->close();

        $this->expectException(LogicException::class);

        $this->channel->publish(new TestEvent('x'));
    }

    public function testCloseReleasesSubscriptions(): void
    {
        $count = 0;
        $this->channel->on('x', function () use (&$count): void {
            $count++;
        });

        $this->channel->close();
        $this->channel->connect();
        $this->channel->publish(new TestEvent('x'));

        $this->assertSame(0, $count);
    }

    public function testCanReconnectAfterClose(): void
    {
        $this->channel->close();
        $this->channel->connect();

        $this->assertTrue($this->channel->isConnected());
    }
}
