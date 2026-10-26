# Patterns

Recipes for the specific problems this project already solves. `AGENTS.md` holds the
general rules; read the matching section here before changing or adding code in that area,
and follow the existing implementation it names.

## API actions

- Each action has three steps: parse the request into a request DTO, call one
  application service, and respond with a response DTO implementing `Arrayable`.
- Request DTOs parse and validate input and throw `InvalidRequestException` (HTTP 400);
  a missing required field is a 400, and missing values are `null`, never sentinel strings.
  In CQRS features the request class parses straight into the Command or Query
  (`PlaceOrderRequest::toCommand($request)`) instead of repeating its fields.
- `Request::routeParameter()` returns the string matched by the route; asking for a
  parameter the route does not declare is a programming error.
- Constrain path parameters in the route (`{id:\d+}`) and map domain exceptions with
  `#[MapException]` instead of `try/catch`.
- The router wraps every route in middleware: `ErrorMiddleware` answers request parsing
  failures (400, 428), and `#[RequireApiKey]` and `#[MapException]` add their policies.
- The public JSON API under `/api/` is sessionless: it never reads the session or cookies
  and identifies resources with unguessable ids or explicit credentials, so it needs no
  CSRF token. Routes for the merchant panel opt into the session with `#[UsesSession]`;
  like browser routes they then require the session's CSRF token, which API clients send
  in the `X-CSRF-Token` header. Protect non-public operations with
  `#[RequireApiKey('<ENV_VARIABLE>')]` (header `X-Api-Key`, compared with `hash_equals`,
  HTTP 401).
- Document every operation in the feature's `openapi/<feature>.yaml`; the functional suite
  validates real responses, including problem details, against it.

## Sessions and CSRF

- GET requests never change state. Mutations, including email confirmation, are POSTs
  with the session's CSRF token in the `X-CSRF-Token` header, validated before any service
  runs; invalid or missing tokens get HTTP 403. The emailed confirmation link opens a React
  page that confirms only when the user presses its button; tests prove that opening the
  link changes nothing and that the POST does, and cover token replay.
- Sessions use strict session ID handling and HttpOnly, SameSite=Lax cookies, Secure on
  HTTPS. Regenerate the session ID after login and logout.
- The only server-rendered page left is the legacy order report; views only produce
  markup and escape output through the shared renderer's helper.

## Panel authentication

- The React panel authenticates with the browser session, not tokens: `GET /api/v1/session`
  reports who is signed in and issues the CSRF token, `POST` signs in, and `DELETE` signs
  out. Registration and email confirmation are `POST /api/v1/registrations` and
  `POST /api/v1/email-confirmations`.
- The session cookie is HttpOnly, so scripts cannot read it, and the panel keeps no
  credentials in browser storage. Integrations use API keys instead (`#[RequireApiKey]`).
- Merchant-only data lives under `/api/v1/panel/` with `#[RequireSignedIn]`, which implies
  the session and answers anonymous requests with 401. When integrations need the same
  data with an API key, add a separate route that delegates to the same private method,
  because route attributes apply to every route of a method.
- Lists page with keyset pagination: the cursor is the public id of the last item, never
  an internal key, and an index matching the sort order serves the next page.

## Registration and confirmation email

- Registration stores the user, the token, and the confirmation message atomically
  through the outbox, so a broker outage cannot lose the email.
- Registering again as an existing unverified user with valid credentials resends the
  confirmation without creating another account.
- Resends are rate-limited with a durable database counter, a typed `int` maximum, and
  the window as a typed `int` constant in seconds. Test the limit and its reset.

## CQRS

Use it only where writes enforce invariants under concurrency (stock, money,
idempotency) and reads need a different shape, as in checkout and orders. Keep simple
CRUD (catalog, carts, authentication) on plain application services.

- Writes: a `Command/` object handled by a `*Handler` that loads the write model, runs in
  a transaction, and returns only an identifier.
- Reads: a `Query/` object handled by a `*Handler` that reads a `ReadModel/` view straight
  from SQL through a read repository, without entities.
- One shared database; no event sourcing and no separate read store.

## Concurrency and transactions

- Run multi-statement work through `TransactionRunnerInterface` with an explicit
  isolation level (`ImmediateTransactionRunner` in unit tests). Transactions are opened in
  services or handlers, never in repositories; repository methods that need one say so.
- Deadlocks and lock wait timeouts retry the whole operation, so a transaction has no side
  effects outside the database, and external services are called before or after it with
  an idempotent stored result.
- Lock competing rows in ascending primary key order, guard counters with conditional
  `UPDATE ... WHERE`, and cover races with a functional test sending parallel requests
  through the web server.

## Idempotency keys

POST operations that create money-related resources accept an `Idempotency-Key` header.
Store the key with a unique index in the same transaction as the resource, return the
existing resource for a repeated key, and answer a key reused for a different request
with HTTP 422.

## Optimistic locking

For client-driven changes to versioned resources: return the version as a strong `ETag`,
require `If-Match` (HTTP 428 when missing), update with `WHERE version = :expected`, and
answer a stale version with HTTP 412. Allowed status changes live on the status enum
(`canTransitionTo()`); forbidden transitions get HTTP 409.

## External providers and webhooks

- Interchangeable providers implement a Strategy interface in the feature (for example
  `Payment/Gateway/`) with implementations under `Infrastructure/`, built by a factory that
  reads a provider's configuration only when that provider is used.
- Verify webhook signatures with `hash_equals` before parsing or touching the database,
  and reject stale timestamps when the provider signs them.
- Process each provider event once by recording its id in an inbox table with a unique
  key in the transaction that applies it, and lock the affected row so concurrent
  deliveries serialize. Cover duplicate and parallel deliveries in functional tests.

## Messaging

- Route a message with `#[Message(transport: 'name')]`; its DSN comes from
  `MESSENGER_<NAME>_DSN`. Handlers are methods marked `#[AsMessageHandler]` whose single
  parameter is the message.
- Code that changes the database never dispatches to the broker directly: it adds messages
  to `OutboxInterface` in the same transaction, and `bin/outbox-relay.php` publishes them,
  delayed ones with a `DelayStamp`. Outbox messages implement `OutboxMessageInterface` with
  a stable `messageType()`.
- Delivery is at least once, so handlers are idempotent.
- Failed messages stay inspectable in the failure queue and are retried with
  `bin/retry-failed-messages.php`, which skips messages it cannot decode. The worker is
  restarted after crashes.
- Queued message class names are a persisted contract: drain the queues or keep the old
  class loadable before renaming or moving one.
- Test handlers and dispatch decisions with mocks, then the end-to-end effect against the
  real broker and service (RabbitMQ, MailHog), polling with a bounded timeout. Mocks of
  broker APIs follow the real contract: an AMQP receiver's `get()` returns at most one
  message per call.

## Caching

Cache reads behind a repository decorator in the feature's `Infrastructure/Cache/`,
marked `#[DefaultImplementation]` and wrapping the database repository through
`#[Autowire]`. Use `StampedeProtectedCache` (a short Redis lock plus probabilistic early
expiration). Keep row locks and writes uncached, invalidate only after commit through
outbox events handled by the worker, and keep TTLs short as a safety net.

## Reporting queries

Reports only read, so they use a plain `ReportService` over the read repository rather
than CQRS query handlers.

For every reporting or performance-sensitive query, add a functional test that loads a
realistic amount of data, runs `ANALYZE TABLE`, and asserts the expected index and access
type in `EXPLAIN FORMAT=JSON`. Filter indexed columns with half-open ranges, never by
wrapping them in functions.

## Legacy refactoring

1. Record a golden master of the observable behavior through the web server, normalizing
   unstable values such as ids.
2. Add failing tests for the defects to fix (SQL injection, XSS).
3. Refactor. The golden master changes only for an intended, reviewed behavior change, and
   old URLs keep working when external links depend on them.

## Strangling a legacy page

1. Import the page as found and record a golden master of what it shows and what it
   stores, through the web server; keep it out of static analysis until it is replaced.
2. Build the replacement on the current architecture: an API operation with the usual
   policies (sign-in, CSRF, optimistic locking, outbox events) and a React screen.
3. Turn the old URL into a route that redirects to the new screen with 301, accepting only
   well-formed input, and delete the old page. Replace the golden master with tests that
   the redirect works and that the inputs that reached the old code change nothing.

The retired `/legacy/product-stock.php` went this way; see its tests in
`backend/tests/Functional/Catalog/`.

## Money and identifiers

- Amounts are `Money\Money` everywhere in the domain, never floats or bare integers;
  stored as an unsigned `BIGINT` in minor units plus a `CHAR(3)` currency code, and exposed
  as `{"amount": "<minor units as string>", "currency": "<code>"}`.
- UUIDs come from `symfony/uid` inside value objects and are stored as `BINARY(16)`.
  Access-granting ids extend `RandomUuid` (version 4); consider version 7 for internal
  time-ordered keys.
