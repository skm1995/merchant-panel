# Vanilla PHP Playground

## Repository layout

| Path | Contents |
|---|---|
| `backend/` | PHP JSON API: `src/`, `tests/`, migrations, worker and relay entry points |
| `frontend/` | React application that uses the backend only through the API |
| `openapi/` | API contract shared by both sides |
| `docker/`, `compose.yaml` | local stack and the test infrastructure used by `bin/ci` |
| `docs/` | patterns and architecture notes |

Backend containers mount the repository at `/app` and work in `/app/backend`; map the
project root to `/app` in your IDE for Xdebug.

## Local development

Copy `.env.example` to `.env`, then start the application and services.
Application configuration (database, RabbitMQ, mailer and public URLs) is read
only from environment variables; local defaults live in `docker/env/*.env` and
`.env.example`, and the application fails fast when a required variable is
missing. The `migrate` service applies database migrations before the web
server, worker, and outbox relay start.

```sh
docker compose up --build -d
```

Set `SESSION_COOKIE_SECURE=true` when the site is served over HTTPS, including
when TLS terminates at a reverse proxy.

Open the merchant panel at `http://localhost:8080`. nginx serves the React app from the
Vite development server, with hot reload, and sends `/api/` and `/legacy/` to PHP, so the
panel and the API share one origin and the session cookie needs no CORS. Regenerate the
API types after changing `openapi/` with
`docker compose --profile tools run --rm frontend-test npm run api:types`.

`bin/ci` also runs the Playwright end-to-end tests against the test stack, which serves
the production build of the panel on port 8081. MailHog is available at
`http://localhost:8025`, and RabbitMQ management is available at
`http://localhost:15672`.

## Product API

The JSON API is documented in [`openapi/`](openapi/openapi.yaml): the root file lists
the paths and each feature keeps its operations and schemas in its own file, with
shared schemas in `shared.yaml`. Run `composer openapi-bundle` for a single-file
`build/openapi.yaml` for tools that need one. Prices are
returned in minor currency units as strings, and errors use RFC 9457 problem
details:

```sh
curl 'http://localhost:8080/api/v1/products?limit=20'
curl 'http://localhost:8080/api/v1/products/1'
```

Pages are ordered by id; pass the returned `next_cursor` as `cursor` to fetch
the next page.

The API is sessionless: carts and orders are addressed by unguessable UUIDs.
A checkout reserves stock for 15 minutes and requires an `Idempotency-Key`
header, so a retried request returns the same order instead of placing a second
one:

```sh
cart=$(curl -s -X POST http://localhost:8080/api/v1/carts | sed -E 's/.*"id":"([^"]+)".*/\1/')
curl -s -X PUT "http://localhost:8080/api/v1/carts/$cart/items/1" -d '{"quantity": 2}'
curl -s -X POST "http://localhost:8080/api/v1/carts/$cart/checkout" -H "Idempotency-Key: $(uuidgen)"
```

## Payments

`POST /api/v1/orders/{id}/payments` starts a payment at a simulated provider
(`fakepay` or `quickpay`) and returns a `checkout_url`. Providers report the
result to `POST /api/v1/payments/webhooks/{provider}`:

- `fakepay` signs the raw body with HMAC-SHA256 in `X-Fakepay-Signature`.
- `quickpay` sends `X-Quickpay-Signature: t=<unix time>,v1=<HMAC of "t.body">`
  and rejects signatures older than five minutes.

A successful payment marks the order paid and turns its reserved stock into
sold stock. A payment for an order that was cancelled meanwhile becomes
`refund_required`. Webhook secrets and checkout URLs come from the
`FAKEPAY_*` and `QUICKPAY_*` environment variables.

Unpaid orders expire after `ORDER_RESERVATION_SECONDS` (15 minutes locally):
checkout schedules an expiry message through the outbox, RabbitMQ holds it
until the reservation ends, and the worker marks the order `expired` and
releases its stock unless it was paid or cancelled first.

## Reports

The reporting API requires the `X-Api-Key` header to match `REPORTS_API_KEY`:

```sh
curl -H 'X-Api-Key: local-reports-api-key' \
  'http://localhost:8080/api/v1/reports/daily-revenue?from=2026-10-01&to=2026-10-31'
curl -H 'X-Api-Key: local-reports-api-key' \
  'http://localhost:8080/api/v1/reports/top-products?from=2026-10-01&to=2026-10-31&limit=5'
```

Both are hand-written SQL over paid orders: the daily report uses a CTE with a
running total (`SUM ... OVER`), and the product report ranks revenue with
`DENSE_RANK`. A functional test checks with `EXPLAIN FORMAT=JSON` that they
range-scan the `orders_status_created_at` index.

The order report page at `/legacy/order-report.php` replaced an old procedural
script that built SQL from request parameters and printed them unescaped. It
now runs a prepared statement, accepts only known statuses and real dates,
escapes all output, and requires the same `X-Api-Key`. A golden master test
recorded from the old script guarantees that its content did not change.

## Product cache

`GET /api/v1/products/{id}` reads products through Redis. Only one process
recomputes a missing entry while others wait for it or keep serving the current
value, and entries are refreshed probabilistically shortly before they expire,
so a popular product never causes a cache stampede on MySQL. Every stock change
records an outbox event, and the worker deletes the affected entries after the
change is committed; entries also expire after 60 seconds.

## Outbox

Features store messages in the `outbox_messages` table in the same transaction
as the data they describe. The `outbox-relay` service (`bin/outbox-relay.php`)
leases batches with `SELECT ... FOR UPDATE SKIP LOCKED`, publishes them to
RabbitMQ, and deletes them; messages due later are published with a delay and
wait in RabbitMQ. Several relays can run side by side, and a message whose relay
crashed is published again after its 30-second lease, so delivery is at least
once and consumers are idempotent.

## Logs and tracing

Every process logs JSON lines to stderr. Each record carries `request_id`: HTTP responses
return it in `X-Request-Id`, and the outbox, relay, and worker pass it on, so one id finds a
request and the emails or expiries it caused.

To search the logs in Kibana, start the observability stack:

```sh
docker compose --profile observability up -d
```

Vector reads the containers' logs through the Docker API and sends them to Elasticsearch,
and Kibana opens at `http://localhost:5601` with an "Application logs" data view. Useful
queries in Discover:

- `app.extra.request_id : "<id from X-Request-Id>"` — everything one request caused
- `app.context.status >= 500` — failed requests
- `app.level_name : "ERROR"` — errors, with their stack traces in `app.context.exception`
- `app.message : "Message handled"` — worker activity with `app.context.duration_ms`

## Services

There is no service configuration file. `ContainerFactory` autowires classes from
their constructor types and discovers the rest in `src/`:

- an application interface with a single implementation is bound to it;
- `#[DefaultImplementation]` chooses among several implementations;
- a `#[ServiceFactory]` method builds the service its return type names, which is
  how vendor services such as `PDO`, the mailer, and the clock are provided;
- `#[Env('NAME')]` injects an environment variable into a constructor parameter.

Controllers are found by their `#[Route]` methods, messages by
`#[Message(transport: ...)]`, and handlers by `#[AsMessageHandler]`. The discovery
result is cached in the system temporary directory and rebuilt when any file in
`src/` changes. To see every binding and where it comes from, run:

```sh
docker compose exec php php bin/container-debug.php
```

## Failed messages

After the worker exhausts its configured retries, messages are stored in the
`failed_messages` RabbitMQ queue. Inspect that queue and the worker logs before
retrying. To requeue all available failed messages to the queues they are routed to, run:

```sh
docker compose exec php php bin/retry-failed-messages.php
```

The command acknowledges a failed message only after it has been dispatched to
their normal queue. If dispatch fails, that message remains in the failed
queue. The command retries every message currently available in that queue.
Messages that can no longer be decoded, for example after a message class was
renamed, are left in the failure queue and reported so they can be inspected
in the RabbitMQ management UI.

An unverified account can request at most five resend attempts in each
24-hour window. The initial confirmation email is not counted as a resend.

## Verification

Run the complete CI verification locally before pushing. GitHub Actions runs
the same script against the same Docker Compose stack:

```sh
bin/ci
```
