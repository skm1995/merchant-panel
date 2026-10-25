# Vanilla PHP Playground

## Local development

Copy `.env.example` to `.env`, then start the application and services.
Application configuration (database, RabbitMQ, mailer and public URLs) is read
only from environment variables; local defaults live in `compose.yaml` and
`.env.example`, and the application fails fast when a required variable is
missing.

```sh
docker compose up --build -d
docker compose exec php composer migrate
```

Set `SESSION_COOKIE_SECURE=true` when the site is served over HTTPS, including
when TLS terminates at a reverse proxy.

The application runs at `http://localhost:8080`. MailHog is available at
`http://localhost:8025`, and RabbitMQ management is available at
`http://localhost:15672`.

## Product API

The JSON API is documented in [`openapi.yaml`](openapi.yaml). Prices are
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

## Outbox

Features store messages in the `outbox_messages` table in the same transaction
as the data they describe. The `outbox-relay` service (`bin/outbox-relay.php`)
leases batches with `SELECT ... FOR UPDATE SKIP LOCKED`, publishes them to
RabbitMQ, and deletes them; messages due later are published with a delay and
wait in RabbitMQ. Several relays can run side by side, and a message whose relay
crashed is published again after its 30-second lease, so delivery is at least
once and consumers are idempotent.

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
