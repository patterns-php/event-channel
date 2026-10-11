<?php

declare(strict_types=1);

namespace Patterns\Tests\Fakes;

use LogicException;
use Patterns\Event;
use Patterns\EventChannel;

/**
 * Reference implementation of {@see EventChannel}, used by the contract test
 * suite. It is the smallest thing that satisfies the documented contract, and
 * it doubles as the example of how the port is meant to be implemented.
 *
 * Deliberately NOT part of `src/`: this package ships the pattern, not an
 * implementation. Concrete transports (Redis, NATS, WebSocket, ...) live in
 * the adapter scope.
 */
final class InMemoryEventChannel implements EventChannel
{
    /** @var array<string, array<int, callable>> */
    private array $handlers = [];

    private int $nextId = 0;

    private bool $connected = false;

    private bool $closed = false;

    /** @var list<Event> */
    public array $published = [];

    public function connect(): void
    {
        $this->connected = true;
        $this->closed = false;
    }

    public function on(string $type, callable $handler): callable
    {
        $this->assertUsable();

        $id = ++$this->nextId;
        $this->handlers[$type][$id] = $handler;

        return function () use ($type, $id): void {
            unset($this->handlers[$type][$id]);
        };
    }

    public function publish(Event $event): void
    {
        $this->assertUsable();

        $this->published[] = $event;

        foreach ($this->matching($event->type()) as $handler) {
            $handler($event);
        }
    }

    public function close(): void
    {
        $this->handlers = [];
        $this->connected = false;
        $this->closed = true;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Handlers registered for the exact type, plus those registered for `*`.
     *
     * @return list<callable>
     */
    private function matching(string $type): array
    {
        // Snapshot: a handler may (un)subscribe while we are dispatching.
        $matched = [];

        foreach ([$type, '*'] as $key) {
            foreach ($this->handlers[$key] ?? [] as $handler) {
                $matched[] = $handler;
            }
        }

        return $matched;
    }

    private function assertUsable(): void
    {
        if ($this->closed) {
            throw new LogicException('Event channel is closed');
        }

        if (!$this->connected) {
            throw new LogicException('Event channel is not connected');
        }
    }
}
