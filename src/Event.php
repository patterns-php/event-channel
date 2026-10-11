<?php

declare(strict_types=1);

namespace Patterns;

/**
 * Event - the contract every event satisfies.
 *
 * One method, forever. `type()` answers exactly one question: *what kind of
 * message is this?* - the routing key that matches a published event against
 * subscriptions and addresses it on a transport.
 *
 * Deliberately NOT part of this contract:
 *   - id / source / timestamp / payload - that is the *envelope*, an
 *     implementation concern (see README, "key vs envelope");
 *   - equality, immutability, validation - that belongs to your event *class*
 *     (a ValueObject, a Unit, a plain DTO), not to the port.
 *
 * The key is a dotted, hierarchical string so it can double as a native
 * subject on every broker (NATS `a.b.*`, Redis `a.b.*`, MQTT `a/b/+`,
 * AMQP `a.b.#`) without this port inventing a pattern language of its own.
 */
interface Event
{
    /**
     * The routing key, e.g. `"stock.updated"` or `"lt.stock.updated"`.
     *
     * Must be stable across processes, languages and versions: it is the part
     * of an event that travels as data and is matched by name on the
     * receiving end.
     */
    public function type(): string;
}
