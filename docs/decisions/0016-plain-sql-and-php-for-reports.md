# 0016. Plain SQL that reduces the data, PHP that computes on the result

Accepted, 2026-10-27.

## Context

The first reports used common table expressions and window functions: a running total from
`SUM() OVER (ORDER BY day)` and a ranking from `DENSE_RANK()`. They worked, but few PHP
developers read them at a glance, and the team maintaining a long-lived shop is better served
by queries anyone can change safely. Speed comes from indexes and from how much data a query
reads, not from these constructs.

## Decision

Report queries use plain SQL: `SELECT`, joins, `WHERE`, `GROUP BY` with `SUM` and `COUNT`,
`ORDER BY`, and `LIMIT`. SQL does the work that shrinks the data, filtering and adding up
millions of rows into a few hundred, and PHP computes on that small result in
[`ReportService`](../../backend/src/Report/Service/ReportService.php): running totals,
positions, and comparisons. Amounts stay integers in minor units in the database, are
summed in SQL exactly, reach PHP as strings, and are combined there only through `Money`.

## Alternatives

- **Window functions and common table expressions.** One query instead of a query and a
  loop, but harder to read and review for little gain on results of a few hundred rows.
- **Computing everything in PHP.** Simple code, but it moves millions of rows out of the
  database; aggregation stays in SQL.

## Consequences

- Each query reads like the report it answers, and the PHP part is unit tested without a
  database.
- The top products report returns exactly the requested number of products; equal revenue
  is ordered by product id instead of sharing a rank.
- Performance is still guarded by `EXPLAIN` tests that require the reporting index.
