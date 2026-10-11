<?php

declare(strict_types=1);

namespace Patterns\Tests;

use PHPUnit\Framework\TestCase;
use Patterns\Event;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class EventTest extends TestCase
{
    /**
     * The envelope (id/source/timestamp/payload) must never creep in here.
     * If this test fails, someone is rebuilding RealtimeEvent in PHP.
     */
    public function testEventIsASingleMethodContract(): void
    {
        $methods = (new ReflectionClass(Event::class))->getMethods();

        $this->assertCount(1, $methods, 'Event must stay a one-method contract.');
        $this->assertSame('type', $methods[0]->getName());
    }

    public function testTypeReturnsAString(): void
    {
        $returnType = (new ReflectionMethod(Event::class, 'type'))->getReturnType();

        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        $this->assertSame('string', $returnType->getName());
    }

    public function testTypeTakesNoArguments(): void
    {
        $this->assertSame(0, (new ReflectionMethod(Event::class, 'type'))->getNumberOfParameters());
    }
}
