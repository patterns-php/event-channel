<?php

declare(strict_types=1);

namespace Patterns;

/**
 * EventChannel - the transport-agnostic contract for named publish/subscribe.
 *
 * This is a *shape*, not a policy. It describes what any channel must be able
 * to do, and nothing about how a particular transport does it. Redis pub/sub,
 * NATS, WebSocket, MQTT, Gun, an in-process emitter - all satisfy it, and a
 * caller written against it never learns which one it got.
 *
 * It is a composition, not a definition: writing is {@see EventPublisher},
 * reading is {@see EventSubscriber}, and all this adds is the transport's
 * lifecycle. Each half is independently useful on its own, and no two contracts
 * in the family declare the same verb - so nothing here can be re-declared or
 * quietly bypassed by a larger shape.
 *
 * Contract:
 *   - `connect()` MUST be called before `on()` / `publish()`; implementations
 *     MUST throw if used while not connected, rather than connecting lazily
 *     (a failed handshake must be visible at the call site that caused it);
 *   - `publish()` MUST NOT swallow transport failures (see EventPublisher);
 *   - `on()` MUST return an idempotent unsubscribe handle (see EventSubscriber);
 *   - after `close()`, `publish()` MUST throw.
 */
interface EventChannel extends EventPublisher, EventSubscriber
{
    /**
     * Establish the connection / handshake with the underlying transport.
     */
    public function connect(): void;

    /**
     * Release resources held by the channel.
     */
    public function close(): void;
}
