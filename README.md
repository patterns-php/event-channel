# Patterns — Event Channel

**A transport-agnostic contract for publishing and subscribing to named events.**

Part of the **Patterns** collection: small, rock-solid, dependency-free building
blocks. One pattern, one package.

- Package: `patterns/event-channel`
- Contracts: `Patterns\Event`, `Patterns\EventPublisher`, `Patterns\EventSubscriber`, `Patterns\EventChannel`, `Patterns\EventProvider`
- Dependencies: **none**
- PHP: `>=8.1`

---

## Install

```bash
composer require patterns/event-channel
```

This package ships **contracts only** — no implementation, no adapters, no
transport. That is deliberate: one stable contract, shared by libraries, is what
makes a complex behaviour cheap to reuse. Redis, NATS, WebSocket, MQTT, Gun, an
in-process emitter — each becomes an adapter behind the same contract, and a
caller written against it never learns which one it got.

## Why

Without a contract, every script picks its own transport *and* its own topology
*and* its own client library:

```php
// Before — the transport leaks into every script
$redis = new \Workerman\Redis\Client('redis://127.0.0.1:6379');   // loop-bound
$redis->subscribe(['stock.updated'], function ($channel, $msg) { /* ... */ });

// a cron script cannot use that: there is no event loop here
$redis = new \Redis();                                            // different client
$redis->pSubscribe(['stock.updated'], function ($pattern, $channel, $msg) { ... });
```

```php
// After — the script only knows the contract
$channel->connect();
$channel->on('stock.updated', function (Event $event): void { /* ... */ });
$channel->publish($event);
```

Whether that is NATS, Redis or a WebSocket is a decision made once, where the
adapter is constructed — not in every call site. That is the whole pattern.

## The contract

```php
namespace Patterns;

interface Event            { public function type(): string; }
interface EventPublisher   { public function publish(Event $event): void; }
interface EventSubscriber  { public function on(string $type, callable $handler): callable; }
interface EventProvider    { public function channel(string $topic): EventChannel; }

interface EventChannel extends EventPublisher, EventSubscriber
{
    public function connect(): void;
    public function close(): void;
}
```

Five contracts, **six methods between them, and no verb in two places**. `Event`
is the message, `EventPublisher` and `EventSubscriber` are its two halves,
`EventChannel` is those halves plus a transport's lifecycle, and `EventProvider`
is the one thing a transport adapter has to implement.

Nothing is declared twice, so nothing can be quietly bypassed: a logger depends
on `EventPublisher` alone, a consuming worker on `EventSubscriber` alone, and
neither is asked for a `connect()` it will never call.

### Contract

| Member | Declared by | Guarantee |
|---|---|---|
| `Event::type()` | `Event` | The routing key. Dotted and hierarchical, so it doubles as a native subject on every broker (`a.b.*`, `a/b/+`, `a.b.#`). |
| `publish($event)` | `EventPublisher` | Deliver to every subscriber of `$event->type()`. Never swallows transport failures. |
| `on($type, $handler)` | `EventSubscriber` | Subscribe by key, or `"*"` for everything. Returns an unsubscribe callable that is idempotent and safe to call from inside the handler. |
| `connect()` | `EventChannel` | Establishes the transport handshake. Must be called before `on()` / `publish()`. |
| `close()` | `EventChannel` | Release resources. `publish()` throws afterwards. |
| `channel($topic)` | `EventProvider` | The channel for a topic. Implementations may return the same channel for the same topic, and must not hand out two different ones. |

## Key vs envelope

The port needs exactly one thing from an event: its **routing key**. Everything
else about an event is envelope, and the port must never need it.

| | Belongs to | Example |
|---|---|---|
| **Key** | the contract — `type(): string` | `lt.stock.updated` |
| **Envelope** | the implementation | `id`, `source`, `occurredAt`, `payload` |
| **Payload** | your domain — anything, including a `ValueObject` or a `Unit` | `new StockUpdated($sku, $qty)` |

That is why `Event` has one method and stays that way. The moment it grows
`id()`, `source()` or `occurredAt()` it has become an envelope interface, and
every implementation is forced into the same envelope shape.

A useful consequence: the key is stable data. Renaming or moving the class that
carries an event does not change the protocol, and the same key works from any
language.

## A shape, not a policy

`publish()` returns nothing and signals failure by **throwing**. The contract
does not return a `Result`, and that is a decision, not an omission:

- a pattern describes a *shape*; failure presentation is a *policy*;
- a throwing contract is satisfiable by any native client, and a caller who
  wants an inspectable outcome wraps it once at their own boundary;
- baking a result convention into the port would force every implementer into
  this collection's conventions — the opposite of "one stable contract, shared
  by libraries".

For the same reason the contract carries no retry, no reconnection, no
backpressure strategy and no state machine. Those are adapter options (set when
the adapter is constructed) and caller decisions.

## What is deliberately absent

| Not in the contract | Where it goes |
|---|---|
| `once()` | Derived: call the unsubscribe handle you already hold after the first delivery. Across processes "once" means dedupe-by-id, which is a shared-state concern. |
| `getState()`, `CONNECTING`/`DISCONNECTING` | A synchronous client has no such states — either the call returned or it threw. |
| `reconnect`, `authToken`, `filters` | Adapter constructor options. |
| `EventSelector` (compound filters, prefixes) | Implementation sugar. The contract defines `"*"` and nothing more. |
| `getId()` | Identity for leases/registries is a shared-state concern. |
| `emit()` | A deprecated alias in the original TypeScript. One name per operation. |
| `getChannels()`, `removeChannel()`, `disconnect()` | Provider bookkeeping. `close()` ends a channel's life, and a caller that cares can keep its own list. |
| `start()`, `stop()` | A server's words for what `connect()` and `close()` already say. |
| `getStats()` | Metrics. Observability, not a contract. |
| a provider options bag (`authToken`, `reconnect`, `transportOptions`, `limits`) | Adapter constructor arguments. Put it in a pattern and the pattern ships policy. |

## Implementing an adapter

1. Implement `Patterns\EventProvider` — one method, `channel(string $topic)`.
   Transport configuration (endpoint, credentials, pool size) belongs in your
   adapter's constructor, exactly as it does for `S3`.
2. Implement `Patterns\EventChannel` for the channels it hands out.
3. Map `$type` onto your transport's native subject (`stock.updated` → a NATS
   subject, a Redis channel, an MQTT topic).
4. Throw on connection or publish failure — do not swallow it.
5. Return an idempotent unsubscribe callable from `on()`.
6. Make `close()` release everything, and `publish()` after `close()` throw.
7. Run the contract test suite against your adapter.

## The test suite is the specification

Because no implementation ships, `tests/EventChannelContractTest.php` is the
executable definition of the contract: routing by key, wildcard delivery,
unsubscribe semantics, self-unsubscribing handlers, lifecycle ordering and the
failure rules. `tests/Fakes/InMemoryEventChannel.php` is the smallest thing that
satisfies it and doubles as a worked example of an adapter.

`tests/EventTest.php` guards the message: it fails if `Event` ever grows a second
method. `tests/EventFamilyTest.php` guards the **family**: it fails if any two
contracts declare the same verb, if `EventChannel` defines anything beyond
`connect()`/`close()`, or if a state, metric or transport verb reappears on any of
them. `tests/EventProviderTest.php` guards the provider port, including the
absence of an options bag.

## PHP notes (vs the TypeScript original)

- **Synchronous, not `Promise`.** PHP has no promises, and the original's
  `connect(): Promise<void>` / `publish(): Promise<void>` cannot be ported
  without adopting a promise library — a hard dependency in a contract package.
  Blocking calls work identically in FPM, cron and CLI; an async adapter can
  wrap its own loop *behind* this interface without the port knowing.
- **`RealtimeEvent` dropped.** Its envelope (`id`/`source`/`timestamp`/`data`)
  exists in TypeScript because JS has no nominal types — the only way to
  describe an event is structurally. In PHP an event is an instance of a class,
  and in the Unit architecture the envelope is already carried by the DNA. It is
  a JS artefact; the envelope stays in the implementation scope.
- **`ChannelState` dropped.** It describes an async event loop, not a channel.
- **No generic `TEvent`.** TypeScript's `EventChannel<TEvent>` is erased at
  runtime anyway. Typing is recovered on the receiving end from the routing key
  itself — a registry/id resolution — which works in both languages.
- **`EventBrokerServer` / `RealtimeServer` dropped — and this is the one that
  mattered.** The original had three interfaces declaring `on()`
  (`EventChannel`, `RealtimeChannel`, `EventBrokerServer`), `emit()` as a
  deprecated synonym for `publish()`, and two more declaring `start()`/`stop()`.
  That is duplicated work by construction: whichever of two identical
  declarations a caller reaches for first, the other becomes dead weight. Here
  each verb has exactly one home, every larger shape is a composition, and a test
  enforces it.

  A server is not a different *shape*. `publish` plus `on` plus a connection is
  the whole of it — the only thing that distinguishes a server from a client is
  who initiates the connection, and that cannot be expressed in a signature. A
  server implementation owns its own `clientId => channel` map and publishes into
  a client's channel to reach it: the address is the channel, not the event.

## Tests

```bash
composer install
composer test
# or
vendor/bin/phpunit
```

## License

MIT.
