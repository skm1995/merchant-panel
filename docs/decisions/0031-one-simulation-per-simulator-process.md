# 0031. The simulator handles one simulation per process

Accepted, 2026-11-03.

## Context

The simulator is a long-lived Messenger worker. Its database connection is opened by the first
run it handles and kept for the process's life, so it sits idle between runs, which can be
hours or days. MySQL closes a connection left idle past `wait_timeout` (8 hours by default). On
the demo, the run of 3 November found that connection closed: its check failed with "MySQL
server has gone away", so nothing saved the check and the panel kept showing the run as running
([0030](0030-interrupted-simulations.md)). Every later run in the same process would fail the
same way until the process restarted.

## Decision

The simulator's worker stops after one message, and Docker (`restart: unless-stopped`) starts
a fresh process for the next run. A process opens its database connection during its run, so
the check comes about a minute after the connection opened, never after hours of idleness. A
run that fails is retried by a new process as well.

## Alternatives

- **Reconnecting when the check meets "gone away".** The connection is one shared object in the
  container, so the handler would need a way to replace it, which touches the container for one
  case.
- **Keeping the process alive and pinging the database during idle time.** It would keep one
  process healthy, but adds a periodic query to every worker that holds a connection.
- **A time limit on the worker.** It bounds the idle time only roughly and depends on how the
  restarts line up with the runs.

## Consequences

- Each run starts a new process, which costs a second or two of startup between runs; no run is
  delayed in a way the merchant can see, since the next run waits for a cool-down anyway.
- The rule is about the simulator only; the worker and the outbox relay keep their connections,
  because they use the database often.
