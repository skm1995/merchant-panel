# 0022. CQRS only for orders and payments

Accepted, 2026-10-25; recorded 2026-10-28. Amended by
[0025](0025-black-friday-through-the-public-api.md), which adds the Black Friday simulation.

## Context

Checkout and payments change state under concurrency: many buyers reserve the same stock,
providers deliver the same webhook several times, and clients retry with an
`Idempotency-Key`. Their reads, an order page and the panel's order list, need shapes that
have little to do with the rules of the writes. The catalog, carts, accounts, and reports have
no such gap between reading and writing.

## Decision

Split writes from reads in `Order` and `Payment` only ([CQRS](../architecture/cqrs.md)):
commands go to handlers that use entities and repositories inside a transaction and return
an identifier or an outcome; queries go to handlers that read read models straight from SQL.
Both paths share one database. Other features stay on plain application services.

## Alternatives

- **CQRS everywhere.** Uniform, but adds a command, a query, and two handlers to every simple
  feature, such as editing a cart line, for no gain.
- **No CQRS.** One service per feature; checkout's locking and idempotency code would then
  also serve the read side and grow around what pages need.
- **A separate read store or event sourcing.** Scales reads independently and keeps a full
  history, but brings eventual consistency and much more infrastructure than one shop needs.

## Consequences

- The concurrency rules live in a small set of command handlers, each tested on its outcomes.
- Read queries can be tuned for their screen, such as cursor pagination, without touching the
  write model.
- A query sees a command's result as soon as it commits, because both read the same tables.
