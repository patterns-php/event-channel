<?php

declare(strict_types=1);

namespace Patterns\Tests\Fakes;

use Patterns\EventChannel;
use Patterns\EventProvider;
use Patterns\Tests\Fakes\InMemoryEventChannel;

/**
 * The smallest thing that is a provider: one method, no configuration.
 *
 * Note what is NOT here and could never be in the port: `topics()` is the fake's
 * own bookkeeping, exposed because a *test* wants to look at it. That is exactly
 * why `getChannels()` does not belong on {@see EventProvider} - a caller that
 * cares keeps its own list, and the provider stays a one-method port.
 */
final class FakeProvider implements EventProvider
{
    /** @var array<string, EventChannel> */
    private array $channels = [];

    public function __construct(private readonly ?EventChannel $channel = null)
    {
    }

    public function channel(string $topic): EventChannel
    {
        return $this->channels[$topic] ??= $this->channel ?? new InMemoryEventChannel();
    }

    /**
     * Not part of the contract - a caller's own bookkeeping.
     *
     * @return list<string>
     */
    public function topics(): array
    {
        return array_keys($this->channels);
    }
}
