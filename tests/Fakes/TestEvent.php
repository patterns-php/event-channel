<?php

declare(strict_types=1);

namespace Patterns\Tests\Fakes;

use Patterns\Event;

/**
 * Minimal event used to exercise the contract: the routing key is whatever you
 * construct it with, so tests can prove that routing follows `type()` and not
 * the concrete class.
 */
final class TestEvent implements Event
{
    public function __construct(private readonly string $type)
    {
    }

    public function type(): string
    {
        return $this->type;
    }
}
