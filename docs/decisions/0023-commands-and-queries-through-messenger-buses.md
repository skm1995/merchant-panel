# 0023. Commands and queries through Messenger buses

Accepted, 2026-10-28. Amends [0022](0022-cqrs-only-for-orders-and-payments.md), under which
controllers called handlers directly.

## Context

Controllers of the CQRS features were built with every handler they might call, and nothing
said in one place whether a command runs at once or later. The project already uses Symfony
Messenger for the worker, and the logs showed which requests came in but not which commands
the application carried out or refused.

## Decision

Send commands through a command bus and queries through a query bus, both synchronous Symfony
Messenger buses behind the project's own
[`CommandBusInterface`](../../backend/src/Shared/Messaging/Bus/CommandBusInterface.php) and
[`QueryBusInterface`](../../backend/src/Shared/Messaging/Bus/QueryBusInterface.php). A handler
is found by name, `XCommand` or `XQuery` to `XHandler`, and built when it is needed. The buses
return the handler's typed result and rethrow its own exception. The command bus logs every
command. A command that runs later implements `OutboxMessageInterface` instead and is recorded
in the outbox, as before ([CQRS](../architecture/cqrs.md#now-or-later)).

## Alternatives

- **Calling handlers directly (before).** No indirection, but controllers depend on every
  handler, and cross-cutting work such as logging is repeated or missing.
- **One bus for both.** Simpler, but commands and queries need different middleware: commands
  are logged, queries are not.
- **Handlers registered by attribute and discovered.** Explicit, but needs a scan of the source
  or a cache; the naming convention the project already follows needs neither.
- **Asynchronous command transport straight to RabbitMQ.** Lets callers fire and forget, but
  is not part of the database transaction; the outbox is.
- **A transaction middleware.** Removes the transaction from each handler, but every handler
  would get the same isolation level, while checkout and expiry need READ COMMITTED.

## Consequences

- Controllers depend on two interfaces instead of their handlers; a new command needs only its
  class and a handler named after it.
- The logs record each command with its duration or its reason for failing, which the
  observability stack can search and chart.
- Handlers stay plain classes with a `handle()` method and keep their unit tests unchanged.
