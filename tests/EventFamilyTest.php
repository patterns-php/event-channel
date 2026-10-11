<?php

declare(strict_types=1);

namespace Patterns\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

use Patterns\Event;
use Patterns\EventChannel;
use Patterns\EventProvider;
use Patterns\EventPublisher;
use Patterns\EventSubscriber;

/**
 * One verb, one home.
 *
 * This is the test that would have caught the duplicated work in the TypeScript
 * original, where `on()` appeared in `EventChannel`, `RealtimeChannel` *and*
 * `EventBrokerServer`, `publish()`/`emit()` both existed (emit had to be marked
 * deprecated as a synonym), and `start()`/`stop()` sat on two more interfaces.
 * Every one of those pairs is a second way to do one thing, and the second way is
 * always the one that gets bypassed.
 *
 * The rule this suite enforces: **no two contracts in the family declare the same
 * verb.** Anything larger is a composition by `extends`, which costs nothing and
 * cannot overlap.
 */
final class EventFamilyTest extends TestCase
{
    // -----------------------------------------------------------------------
    // The rule
    // -----------------------------------------------------------------------

    public function testNoTwoContractsDeclareTheSameVerb(): void
    {
        /** @var array<string, list<class-string>> $owners */
        $owners = [];

        foreach (self::family() as $interface) {
            foreach (self::declaredMethods($interface) as $method) {
                $owners[$method][] = $interface;
            }
        }

        foreach ($owners as $method => $interfaces) {
            $this->assertCount(
                1,
                $interfaces,
                sprintf(
                    '%s() is declared by %s - two contracts, one verb, and one of them will end up bypassed',
                    $method,
                    implode(' and ', $interfaces),
                ),
            );
        }
    }

    public function testTheWholeFamilyDeclaresSixMethodsBetweenThem(): void
    {
        $methods = [];

        foreach (self::family() as $interface) {
            $methods = [...$methods, ...self::declaredMethods($interface)];
        }

        // Event.type, EventPublisher.publish, EventSubscriber.on,
        // EventChannel.connect, EventChannel.close, EventProvider.channel.
        // Six methods, five contracts, and no overlap - that is the whole family.
        $this->assertCount(6, $methods);
    }

    // -----------------------------------------------------------------------
    // Composition, not definition
    // -----------------------------------------------------------------------

    public function testEventChannelIsACompositionAndDefinesNothingButLifecycle(): void
    {
        $this->assertSame(
            ['close', 'connect'],
            self::declaredMethods(EventChannel::class),
            'the channel must only add the transport lifecycle - writing and reading are inherited',
        );
    }

    public function testEventChannelIsBothHalves(): void
    {
        $channel = new ReflectionClass(EventChannel::class);

        $this->assertTrue($channel->implementsInterface(EventPublisher::class), 'a channel writes');
        $this->assertTrue($channel->implementsInterface(EventSubscriber::class), 'and reads');
    }

    public function testEachHalfIsSeparateFromTheTransport(): void
    {
        $publisher = new ReflectionClass(EventPublisher::class);
        $subscriber = new ReflectionClass(EventSubscriber::class);

        $this->assertFalse($publisher->hasMethod('connect'), 'writing needs no connection concept');
        $this->assertFalse($publisher->hasMethod('on'), 'and cannot read');
        $this->assertFalse($subscriber->hasMethod('publish'), 'and the reader cannot write');
        $this->assertFalse($subscriber->hasMethod('connect'), 'nor does the verb "subscribe" imply a transport');
    }

    public function testNoContractInTheFamilyIsAStateMachine(): void
    {
        foreach (self::family() as $interface) {
            foreach (['getState', 'getId', 'getStats', 'emit', 'broadcast', 'sendTo', 'start', 'stop'] as $rejected) {
                $this->assertFalse(
                    (new ReflectionClass($interface))->hasMethod($rejected),
                    sprintf('%s::%s() - state, metrics and transport verbs were deliberately left out', $interface, $rejected),
                );
            }
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return list<class-string>
     */
    private static function family(): array
    {
        return [
            Event::class,
            EventPublisher::class,
            EventSubscriber::class,
            EventChannel::class,
            EventProvider::class,
        ];
    }

    /**
     * Only what the interface declares itself - inherited methods belong to
     * whoever declared them, which is the whole point.
     *
     * @return list<string>
     */
    private static function declaredMethods(string $interface): array
    {
        $own = [];

        foreach ((new ReflectionClass($interface))->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $interface) {
                $own[] = $method->getName();
            }
        }

        sort($own);

        return $own;
    }
}
