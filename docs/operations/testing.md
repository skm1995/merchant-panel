# Testing

The project is built test first and outside in: a feature starts with a failing functional
test of its behavior from the outside, and the code with many cases below it is driven by unit
tests. Each step is the smallest change that passes, followed by a refactoring with the tests
green. Tests describe behavior, including failure paths and security rules, rather than
implementation details: they check results and stored state, not which methods were called,
so a refactoring that keeps the behavior keeps the tests green.

## The test layers

| Layer | Tool | Runs against | Guards |
|---|---|---|---|
| Backend unit | PHPUnit | classes built directly, with fakes only for boundaries such as the clock and payment gateways; cross-cutting HTTP behavior through the real router and middleware, reading the response from [`RecordingResponseWriter`](../../backend/tests/Unit/Support/RecordingResponseWriter.php) | calculations, domain rules, validation, error mapping, middleware order, reactions to failing dependencies |
| Backend functional | PHPUnit with an HTTP client | the test stack through Caddy, a real MySQL, Redis, RabbitMQ, worker, and Mailpit | contracts, SQL, locking and concurrency, messaging, security paths |
| Frontend unit | Vitest, Testing Library, MSW | components in jsdom, the API faked at the network boundary | screens, the session, the API client |
| End to end | Playwright | a real browser against the test stack | the main journeys across both sides |
| Load | k6 | the test stack | latency, error rate, and stock invariants under contention |
| Static | PHPStan level 8, Deptrac, PHP CS Fixer, `tsc`, ESLint, Prettier | the code | types, architecture layers, style |

## What goes where

| Unit tests | Functional tests |
|---|---|
| Calculations: money, totals, running totals, the sales calendar | The main path of every endpoint through the real server and database |
| Domain rules and state transitions | Every SQL query, including `EXPLAIN` checks of the reports |
| Parsing and validation of input, with every invalid case | Security paths: missing session, rejected CSRF token, missing merchant role |
| Edge cases: empty lists, ties, zero, limits | Concurrency: buyers of the last unit, repeated webhooks |
| Reactions to a failing dependency, such as the broker | Messaging through the outbox, broker, and worker |

Unit tests live in the mirrored namespace under `tests/Unit`; a class that only passes calls
on or maps data has none, because the functional tests already cover it. Functional tests
live under `tests/Functional` and run only against the test stack. The unit suite runs in
a few seconds, the functional suite in about twenty. Unit tests hash test passwords with the
lowest bcrypt cost ([`CheapPasswordHash`](../../backend/tests/Unit/Support/CheapPasswordHash.php)),
because the production cost takes a fifth of a second per hash; the one test that compares the
timing of sign-in attempts keeps the real cost.

## Every route is tested

[`RouteContractTest`](../../backend/tests/Unit/Shared/Http/RouteContractTest.php) checks that
every API route has an operation in the OpenAPI contract and the other way round, and after
the functional suite [`check-api-calls.php`](../../backend/tools/check-api-calls.php) checks
that the functional tests called every operation
([0020](../decisions/0020-every-route-in-the-contract-and-the-tests.md)). A new endpoint
without a contract entry or a functional test fails `bin/ci` with its name.

## Coverage

`bin/ci` merges the coverage of the unit, functional, and end-to-end tests, including every
request the test web server handles, and fails when it drops below a minimum that trails the
measured value by about two points ([0019](../decisions/0019-merged-coverage-with-a-ratchet.md)).
The HTML reports land in `build/coverage/html/` and `frontend/coverage/`, and GitHub Actions
keeps them as an artifact of each run. Raise the minimum in
[`check-coverage.php`](../../backend/tools/check-coverage.php) or
[`vite.config.ts`](../../frontend/vite.config.ts) when coverage grows; never lower it to make
a change pass.

## What the functional tests check

- **The contract.** Every response is validated against the OpenAPI description, so the
  documentation cannot drift from the code.
- **Concurrency.** Several requests are sent at once: many buyers of the last unit (exactly
  one succeeds), checkouts of overlapping products (no deadlock), five concurrent deliveries
  of one webhook (applied once), concurrent cancellations (one succeeds).
- **Asynchronous work.** Registration until the email arrives in Mailpit, and order expiry
  through the real broker and worker.
- **A Black Friday run.** A whole [simulation](../flows/black-friday.md) through the API,
  the simulator, and the database, checking that every unit sold was paid for.
- **Security paths.** CSRF rejection, access control for every panel route, SQL injection and
  script injection in the legacy report, security headers, and that GET requests change
  nothing.
- **Recovery.** An unreachable Redis is reported as an unavailable cache. Broker failures are
  covered by unit tests: the relay keeps a message it could not publish, and the failed
  message retrier keeps one it could not dispatch again
  ([`OutboxRelayTest`](../../backend/tests/Unit/Shared/Messaging/Outbox/OutboxRelayTest.php),
  [`FailedMessageRetrierTest`](../../backend/tests/Unit/Shared/Messaging/FailedMessageRetrierTest.php)).
- **Query plans.** `EXPLAIN` checks that the report queries use their index.

Functional tests use unique data (random email addresses, SKU prefixes) and remove it
afterwards, so they can run repeatedly against the same test database.

## Frontend tests

[`renderApp`](../../frontend/src/test/render.tsx) renders the whole app at a path, so tests go
through routing and the session like a user. [MSW](https://mswjs.io) answers API requests;
a request without a handler fails the test, so no test depends on an API it did not
declare. The end-to-end tests sign in with the shared demo account, which `bin/ci` recreates
in the test database before they run.

## The load test

[`bin/loadtest`](../../bin/loadtest) seeds products with less stock than the simulated demand
and runs [`loadtest/black-friday.js`](../../loadtest/black-friday.js): shoppers browse at a
constant rate while a ramping stream checks out, pays, and receives every provider webhook
twice. k6 fails the run when more than 1% of requests fail unexpectedly or p95 latency
exceeds 800 ms. Afterwards the script waits for unpaid reservations to expire and checks in
MySQL that no product has negative stock or leftover reservations and that exactly the paid
quantities were sold.

## Lessons from flaky tests

- Tests reach the test stack over the Compose network (`http://caddy-test`), not through a
  published host port: Docker Desktop's port forwarding occasionally dropped requests while
  containers were recreated.
- Frontend async queries wait up to five seconds instead of one, which a busy CI machine can
  exceed.

## Limitations

- The end-to-end tests cover the main journeys only.
- There is no mutation testing or coverage threshold.
