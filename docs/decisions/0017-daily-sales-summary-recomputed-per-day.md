# 0017. A daily sales summary, recomputed a day at a time

Accepted, 2026-10-28.

## Context

Reports over months or years would add up millions of order lines on every request. Most of
that work repeats: the sales of a past day no longer change.

## Decision

Keep `daily_product_sales`, with units and revenue of paid orders per day, product, and
currency. When an order is paid, [`OrderSettlement`](../../backend/src/Order/Service/OrderSettlement.php)
adds an `OrderPaidMessage` to the outbox, and
[`RefreshDailySalesHandler`](../../backend/src/Analytics/Service/RefreshDailySalesHandler.php)
recomputes the order's day: it deletes the day's rows and inserts them again from the paid
orders with `INSERT … SELECT … GROUP BY`, in one transaction at READ COMMITTED. The sales
history generator refreshes each day it creates.

## Alternatives

- **Adding each order to a counter** (`units = units + n`). Cheaper per message, but a
  repeated delivery would count an order twice; it would need a table of applied orders.
- **A database trigger.** Always in sync, but hidden from the application code and its tests,
  and it would slow every payment down.
- **A nightly rebuild.** Simple, but reports would lag a day behind.

## Consequences

- A repeated or late message recomputes the same numbers, so the handler is idempotent
  without extra bookkeeping.
- The summary lags behind a payment by the time the worker needs, about a second.
- Recomputing a day reads only that day's orders through `orders_status_created_at_totals`; even
  Black Friday has tens of thousands of them.
