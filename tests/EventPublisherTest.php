<?php

declare(strict_types=1);

namespace Patterns\Tests;

use PHPUnit\Framework\TestCase;
use Patterns\EventChannel;
use Patterns\EventPublisher;
use Patterns\Tests\Fakes\PublisherOnly;
use Patterns\Tests\Fakes\TestEvent;
use ReflectionClass;

final class EventPublisherTest extends TestCase
{
    /**
     * The write half must remain usable without the read half - that is the
     * whole point of splitting the port.
     */
    public function testPublisherIsSatisfiableWithoutAChannel(): void
    {
        $publisher = new PublisherOnly();

        $this->assertInstanceOf(EventPublisher::class, $publisher);
        $this->assertNotInstanceOf(EventChannel::class, $publisher);

        $publisher->publish(new TestEvent('anything'));

        $this->assertCount(1, $publisher->published);
    }

    public function testChannelIsAPublisher(): void
    {
        $this->assertTrue(
            (new ReflectionClass(EventChannel::class))->implementsInterface(EventPublisher::class),
            'EventChannel must extend EventPublisher so any publisher can be substituted.'
        );
    }

    /**
     * `publish()` returns nothing: failure is signalled by throwing, so the
     * port never forces a Result convention on its implementers.
     */
    public function testPublishReturnsNothing(): void
    {
        $returnType = (new \ReflectionMethod(EventPublisher::class, 'publish'))->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('void', $returnType->getName());
    }
}
