# 0002. Organize code by feature, with layers enforced by Deptrac

Accepted, 2026-10-25; enforced with Deptrac since 2026-10-26.

## Context

Grouping code by type (`Controller/`, `Service/`, `Repository/` at the top) spreads one
business feature across the whole tree and lets any class depend on any other.

## Decision

Give each feature its own directory (`Auth`, `Cart`, `Catalog`, `Order`, `Payment`, `Report`)
with the same layer directories inside, and check the allowed dependencies between layers with
[Deptrac](../../backend/deptrac.yaml) in `bin/ci`. Application services depend on ports such
as `CurrentUserInterface`; adapters in `Infrastructure/` implement them.

## Alternatives

- **Type-first directories.** Familiar, but they hide feature boundaries.
- **Separate packages per feature.** Stronger isolation, but too heavy for a project this size.

## Consequences

- A feature can be read in one place, and a wrong dependency fails CI instead of slipping in
  during review.
- Cross-feature calls are explicit, for example the payment feature settling orders through
  `OrderSettlement`.
- See [Backend](../architecture/backend.md) for the layers and rules.
