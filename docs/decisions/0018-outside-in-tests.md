# 0018. Outside-in tests: functional tests lead, unit tests cover logic

Accepted, 2026-10-28.

## Context

The project began with a unit test for every class, built with mocks that expected exact
calls, such as a repository's `transition()` with its four arguments. Those tests failed on
refactorings that kept the behavior, repeated what the functional tests already proved for
controllers and services that only pass calls on, and could not catch a wrong SQL query,
because a mocked repository accepts anything.

## Decision

Tests are written outside in. A feature starts with a failing functional test of its
behavior from the outside: an API call through the real web server, database, cache, and
broker. Unit tests cover the code with many cheap cases below it: calculations, domain rules,
state transitions, input validation, and reactions to a failing dependency. Both kinds
assert results and stored state, not which methods were called; only boundaries a test does
not control (the clock, payment gateways, the mailer, the broker) are replaced, preferably by
small fakes such as `FrozenClock` and `RecordingOutbox`. A call is expected only when it is
the effect under test, such as a write or a message.

Controllers, repositories, and classes that only delegate get no unit tests of their own.
Every route has a functional test of its main path and its security and failure paths.
[Testing](../operations/testing.md) lists what goes where.

## Alternatives

- **A unit test per class (the test pyramid).** Fast and precise about where a bug is, but
  with mocks it pins the implementation and still misses SQL and wiring mistakes.
- **Functional tests only.** Proves the whole, but every edge case costs an HTTP request and a
  database round trip, and failures point at a whole request instead of a rule.

## Consequences

- A refactoring that keeps the behavior keeps the tests green.
- Edge cases stay cheap: the unit suite runs in a few seconds, the functional suite in about
  twenty.
- Unit-only coverage looks low (about 72% of lines), because it is not meant to cover
  everything; the merged coverage of all suites is the number that matters
  ([0019](0019-merged-coverage-with-a-ratchet.md)).
