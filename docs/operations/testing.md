# Testing

The project is built test first: a failing test, the smallest change that passes it, then a
refactoring with the tests green. Tests describe behavior, including failure paths and
security rules, rather than implementation details.

## The test layers

| Layer | Tool | Runs against | Guards |
|---|---|---|---|
| Backend unit | PHPUnit | classes built directly with test doubles; controllers through [`HttpHarness`](../../backend/tests/Unit/Support/HttpHarness.php), which dispatches through the real router | rules, validation, error mapping, middleware order |
| Backend functional | PHPUnit with an HTTP client | the test stack through Caddy, a real MySQL, Redis, RabbitMQ, worker, and Mailpit | contracts, SQL, locking and concurrency, messaging, security paths |
| Frontend unit | Vitest, Testing Library, MSW | components in jsdom, the API faked at the network boundary | screens, the session, the API client |
| End to end | Playwright | a real browser against the test stack | the main journeys across both sides |
| Load | k6 | the test stack | latency, error rate, and stock invariants under contention |
| Static | PHPStan level 8, Deptrac, PHP CS Fixer, `tsc`, ESLint, Prettier | the code | types, architecture layers, style |

Each production class has a test class in the mirrored namespace under `tests/Unit`.
Functional tests live under `tests/Functional` and run only against the test stack.

## What the functional tests check

- **The contract.** Every response is validated against the OpenAPI description, so the
  documentation cannot drift from the code.
- **Concurrency.** Several requests are sent at once: many buyers of the last unit (exactly
  one succeeds), checkouts of overlapping products (no deadlock), five concurrent deliveries
  of one webhook (applied once), concurrent cancellations (one succeeds).
- **Asynchronous work.** Registration until the email arrives in Mailpit, and order expiry
  through the real broker and worker.
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
