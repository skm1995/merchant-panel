# 0004. Contract-first API with OpenAPI

Accepted, 2026-10-25.

## Context

The backend and the React panel are developed side by side. Without a shared contract, a
change on one side breaks the other at runtime.

## Decision

Describe every operation in [`openapi/`](../../openapi/openapi.yaml), one file per feature
with shared schemas, and make it the single source of truth: the backend's functional tests
validate every real response against it, and the frontend generates its TypeScript types from
it. `bin/ci` checks that the generated types are up to date. Errors use RFC 9457 problem
details.

## Alternatives

- **Code-first generation from PHP annotations.** Keeps the contract next to the code, but
  makes it a by-product rather than a design.
- **No formal contract.** Fastest at first, most fragile later.

## Consequences

- A contract change shows up in the review as a diff of the YAML, and a mismatch fails CI on
  both sides.
- The contract lives at the root of the repository because both sides own it.
