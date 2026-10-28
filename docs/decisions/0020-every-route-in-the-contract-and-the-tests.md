# 0020. Every route is in the contract and called by a functional test

Accepted, 2026-10-28.

## Context

[Outside-in tests](0018-outside-in-tests.md) make the functional tests responsible for every
endpoint, and the OpenAPI contract is the single source of truth for the API. Both were rules
in `AGENTS.md`: a route added without a contract entry or without a functional test went
unnoticed until someone looked.

## Decision

Two checks enforce the rules in `bin/ci`:

- [`RouteContractTest`](../../backend/tests/Unit/Shared/Http/RouteContractTest.php) compares
  the `#[Route]` attributes under `/api/` with the operations of `openapi/openapi.yaml`, in
  both directions, ignoring how path parameters are named;
- the functional tests' HTTP clients note every request in a log, and
  [`check-api-calls.php`](../../backend/tools/check-api-calls.php) fails when an operation of
  the contract was never called after the functional suite.

Together they leave no route without a contract and no operation without a test that goes
through the real web server.

## Alternatives

- **Coverage of controller actions.** Shows that an action ran, but not which route led to
  it, and depends on the coverage run.
- **Generated contract tests (Schemathesis, Dredd).** They check responses against the
  contract with generated input, which complements the hand-written tests but does not show
  whether the project's own tests reach every endpoint.

## Consequences

- Adding an endpoint means adding its operation to the contract and a functional test, or
  `bin/ci` names what is missing.
- The log check runs only after the whole functional suite; running a single test file does
  not fail it.
