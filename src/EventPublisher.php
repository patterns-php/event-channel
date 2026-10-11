<?php

declare(strict_types=1);

namespace Patterns;

/**
 * EventPublisher - the write half of the event channel.
 *
 * Split out from {@see EventChannel} because publishing and subscribing are
 * independently useful: a logger publishes its records to a broker and never
 * subscribes to anything.
 *
 * Failure (broker down, channel closed, payload not serializable) is signalled
 * by throwing. This port deliberately does *not* return a `Result`: see README,
 * "a shape, not a policy".
 */
interface EventPublisher
{
    /**
     * Publish an event to every subscriber of its {@see Event::type()}.
     *
     * Implementations MUST NOT swallow transport failures.
     */
    public function publish(Event $event): void;
}
