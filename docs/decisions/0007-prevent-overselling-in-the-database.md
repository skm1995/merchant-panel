# 0007. Prevent overselling with ordered row locks and conditional updates

Accepted, 2026-10-25.

## Context

Many buyers check out the same products at the same moment, as in a sale. Checking stock in
PHP and then updating it lets two requests both see the last unit; locking products in a
different order in two transactions can deadlock.

## Decision

Reserve stock inside the checkout transaction in
[`StockReservation`](../../backend/src/Catalog/Service/StockReservation.php): lock the
product rows with `SELECT … FOR UPDATE` in ascending id order, check availability, and update
with a condition that repeats the check in SQL, such as
`reserved = reserved + n WHERE stock - reserved >= n`. A reservation becomes sold stock when
the order is paid and returns when it is cancelled or expires.

## Alternatives

- **Optimistic retries on a version column.** Many retries under contention, exactly when the
  load is highest.
- **A queue that serializes all checkouts.** Correct, but slower and more moving parts.
- **Redis counters.** Fast, but a second source of truth next to MySQL.

## Consequences

- The database guarantees the invariant even if application code has a bug.
- Ordered locking prevents deadlocks between checkouts with overlapping products, which a
  functional test runs concurrently.
- `bin/loadtest` checks after thousands of concurrent checkouts that nothing was oversold.
- Hot products serialize their checkouts on one row, which limits throughput per product.
