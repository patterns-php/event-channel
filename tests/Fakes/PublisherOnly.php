<?php

declare(strict_types=1);

namespace Patterns\Tests\Fakes;

use Patterns\Event;
use Patterns\EventPublisher;

/**
 * Proves the interface segregation holds: `EventPublisher` is satisfiable on
 * its own, with no subscribing and no lifecycle. This is the shape a logger
 * adapter needs.
 */
final class PublisherOnly implements EventPublisher
{
    /** @var list<Event> */
    public array $published = [];

    public function publish(Event $event): void
    {
        $this->published[] = $event;
    }
}
