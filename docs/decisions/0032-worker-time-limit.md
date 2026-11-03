# 0032. The worker restarts on a time limit

Accepted, 2026-11-03. Supersedes the worker part of [0031](0031-one-simulation-per-simulator-process.md).

## Context

[0031](0031-one-simulation-per-simulator-process.md) left the worker a long-lived process because
it uses the database often. It does so only while messages arrive: a quiet night leaves its
connection idle past MySQL's `wait_timeout`. The first message after that fails with
"inactivity", its retries in the same process fail with "MySQL server has gone away", and it
lands in `failed_messages`. So does every later message that needs the database, until the
process restarts, and a message that expires an order leaves that order's stock reserved.

## Decision

The worker exits after `WORKER_TIME_LIMIT_SECONDS`, an hour, once it has finished the message in
hand, and Docker starts a fresh one. A connection is then never older than the limit, which
stays far below `wait_timeout`. The queues hold the messages for the moment between processes.

## Alternatives

- **One message per process, as the simulator does.** Simple, but a burst of emails or expiries
  would pay for a new process each.
- **Reconnecting when a query meets a closed connection.** The connection is one shared object
  in the container ([0031](0031-one-simulation-per-simulator-process.md)), and a query cannot be
  repeated blindly inside a transaction.
- **Raising `wait_timeout`.** It moves the limit, and a quiet weekend still crosses it.

## Consequences

- A connection closed for idleness no longer sends messages to `failed_messages`.
- The time limit is configuration, so a test can see a real worker process end on its own.
- Messages already in `failed_messages` stay there until an operator
  [retries them](../operations/runbooks.md#retry-failed-messages).
