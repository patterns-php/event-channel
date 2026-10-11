<?php

declare(strict_types=1);

namespace Patterns;

/**
 * EventProvider - the port a transport adapter implements.
 *
 * One method, and it is the whole contract: hand back the channel for a topic.
 * `Local`, `Redis`, `Nats`, `WebSocket`, `Gun` - whichever it is, a caller written
 * against this never learns which one it got, and swapping the transport is a
 * constructor argument rather than a branch in every feature. The same shape as
 * `IFilesystem`, and the same reason for it.
 *
 * Deliberately NOT here, and each for a reason already settled in this family:
 *
 *   - `getChannels()` - introspecting the provider's own registry is the
 *     `getState()`/`getId()` call again: state is the implementation's business,
 *     and a caller that cares can hold its own channels.
 *   - `removeChannel($channel)` - `EventChannel::close()` already ends a channel's
 *     life; the provider can observe that, and two ways to end one thing is how
 *     the original grew a `disconnect()` that fought with `close()`.
 *   - `disconnect()` - that is `close()` on every channel the provider handed out,
 *     which the provider can do without a contract telling it to.
 *   - an options bag (`authToken`, `reconnect`, `transportOptions`, ...) - every
 *     field of such a bag is credentials, retry policy or transport passthrough.
 *     That is an adapter's constructor argument, like S3's config. Ship it in a
 *     pattern and you have shipped policy.
 *
 * A provider is created with its configuration and asked for channels. It is not
 * a factory that reports on itself.
 */
interface EventProvider
{
    /**
     * The channel for a topic.
     *
     * Implementations MAY return the same channel for the same topic on repeated
     * calls, and MUST NOT return a channel in a different state than the last one
     * they returned for it.
     */
    public function channel(string $topic): EventChannel;
}
