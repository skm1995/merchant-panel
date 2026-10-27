# 0001. Build without a framework

Accepted, 2026-10-25.

## Context

The project shows backend engineering to employers. A framework such as Symfony or Laravel
provides routing, dependency injection, sessions, CSRF protection, and messaging, but hides
how they work; a reviewer cannot tell whether the author understands them.

## Decision

Write the application in plain PHP 8.5 and build the infrastructure it needs in
[`backend/src/Shared`](../../backend/src/Shared): the container, the router and middleware,
sessions, CSRF tokens, the outbox, and rate limiting. Use focused libraries where writing the
code teaches nothing new: Symfony Messenger, Mailer, and UID, moneyphp, Monolog, and Phinx.

## Alternatives

- **Symfony.** Faster to build, familiar to many teams, but the interesting parts would be
  configuration rather than code.
- **A micro-framework such as Slim.** Still hides the middle layers that the project wants to
  show.

## Consequences

- Every mechanism is visible, tested, and documented, which is the point of the project.
- The code must be kept as disciplined as a framework would make it: PHPStan level 8,
  Deptrac, and the [coding patterns](../patterns.md) do that work.
- New features cost more than with a framework, and new team members have to learn the
  project's own conventions.
