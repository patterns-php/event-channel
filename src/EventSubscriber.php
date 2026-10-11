<?php

declare(strict_types=1);

namespace Patterns;

/**
 * EventSubscriber - the read half of the event channel.
 *
 * Split out of {@see EventChannel} for exactly the reason {@see EventPublisher}
 * is: reading and writing are independently useful, and a contract that promises
 * both is bigger than either caller needs. An agent publishes what it ran and
 * subscribes to nothing; a worker consumes and publishes nothing.
 *
 * The split has a second effect, which is the point of it: **no two contracts in
 * this family declare the same verb.** `publish` lives here nowhere, `on` lives
 * nowhere else, and every larger shape is a composition. In the TypeScript
 * original, `EventChannel`, `RealtimeChannel` and `EventBrokerServer` all declared
 * `on()`, and `RealtimeChannel` had to deprecate `emit()` because it was
 * `publish()` wearing a second name. One verb in two places is how a family grows
 * two ways to do one thing.
 *
 * Failure (not connected, closed, transport down) is signalled by throwing. This
 * port deliberately does *not* return a `Result`: see README, "a shape, not a
 * policy".
 */
interface EventSubscriber
{
    /**
     * Subscribe to events by routing key.
     *
     * @param string                $type    Routing key, or `"*"` for every event.
     *                                       Richer selectors (prefixes, compound
     *                                       filters) are an implementation's
     *                                       choice - the port only defines `"*"`.
     * @param callable(Event): void $handler Invoked for every matching event.
     *
     * @return callable(): void Unsubscribe. Idempotent: calling it twice is a
     *                          no-op, and calling it from inside `$handler` is
     *                          allowed.
     */
    public function on(string $type, callable $handler): callable;
}
