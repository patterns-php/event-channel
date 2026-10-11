# Changelog

All notable changes to `patterns/event-channel` are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) ·
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-11

### Added

- `Patterns\Event` - a one-method contract (`type(): string`) describing the
  routing key of an event.
- `Patterns\EventPublisher` - the write half (`publish()`), so publishers need
  not depend on subscribing.
- `Patterns\EventSubscriber` - the read half (`on()`), so a consuming worker need
  not depend on publishing either.
- `Patterns\EventProvider` - the one-method port a transport adapter implements
  (`channel(string $topic): EventChannel`).
- `Patterns\EventChannel` - the composition: `EventPublisher` + `EventSubscriber`
  + `connect()` / `close()`.
- `tests/EventFamilyTest.php` - enforces that no two contracts in the family
  declare the same verb, and that no state, metric or transport verb creeps back
  in.
- A contract test suite that doubles as the executable specification, with
  in-test fakes (`tests/Fakes/`). No implementation ships with this package.

[1.0.0]: https://github.com/patterns-php/event-channel/releases/tag/v1.0.0
