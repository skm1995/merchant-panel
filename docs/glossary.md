# Glossary

Terms used in the code and in this documentation, with where they matter in the project.

| Term | Meaning | In this project |
|---|---|---|
| **ADR** (architecture decision record) | A short document that records one decision, its context, alternatives, and consequences | [`decisions/`](decisions/README.md) |
| **At-least-once delivery** | A message is delivered one or more times, never zero; handlers must tolerate duplicates | The outbox relay and RabbitMQ; [ADR 0006](decisions/0006-transactional-outbox.md) |
| **Cache stampede** | Many requests recompute the same expired cache entry at once and overload the database | Prevented with a short lock and probabilistic early refresh in `StampedeProtectedCache` |
| **Characterization test, golden master** | A test that records what existing code does, so a refactoring can prove it changed nothing | [Legacy](flows/legacy.md) |
| **Conditional update** | An `UPDATE … WHERE` that repeats a business rule, so the database refuses a change that breaks it | Stock reservation and status changes; [ADR 0007](decisions/0007-prevent-overselling-in-the-database.md) |
| **Content-Security-Policy (CSP)** | A response header that tells the browser which sources may provide scripts, styles, and other content | [`headers.caddy`](../docker/caddy/headers.caddy) |
| **Correlation id, request id** | An identifier that follows one request through every process and log line | [Observability](operations/observability.md) |
| **CQRS** | Command query responsibility segregation: separate code paths for changing state and for reading it | `Command/` and `Query/` in `Order` and `Payment`; see [CQRS](architecture/cqrs.md) |
| **CSRF** | Cross-site request forgery: another site makes the browser send an authenticated request | Prevented with a token in a custom header; [Authentication](flows/authentication.md) |
| **Cursor pagination** | Paging by "items after this one" instead of page numbers, stable while items are added | Orders and products lists |
| **Deptrac** | A tool that checks the dependencies between architectural layers | [`deptrac.yaml`](../backend/deptrac.yaml) |
| **ETag, If-Match** | A response header with the resource's version, and a request header that applies a change only to that version | Optimistic locking of orders and products |
| **FastCGI** | The protocol a web server uses to hand a request to PHP-FPM | Caddy to PHP-FPM |
| **HMAC** | A keyed hash that proves a message comes from someone who knows the secret | Webhook signatures and order access tokens |
| **HSTS** | A header that tells browsers to use HTTPS for the domain from now on | Production responses |
| **Idempotency key** | A client-chosen key that makes a retried request return the first result instead of repeating it | Checkout and payment start |
| **Inbox** | A table of processed event ids that makes a repeated message or webhook a no-op | `payment_webhook_events` |
| **Middleware** | A layer that sees a request before the handler and the response after it | [Request lifecycle](architecture/request-lifecycle.md) |
| **Optimistic locking** | Detecting concurrent changes by version instead of holding a lock | [ADR 0008](decisions/0008-idempotency-keys-and-optimistic-locking.md) |
| **Outbox (transactional)** | A table where messages are stored in the same transaction as the data, then published by a separate process | [ADR 0006](decisions/0006-transactional-outbox.md) |
| **Port, adapter** | An interface the application depends on, and the class that implements it with a concrete technology | `CurrentUserInterface` and the session adapter |
| **Problem details (RFC 9457)** | A standard JSON format for HTTP errors: `type`, `title`, `status`, `detail` | Every API error |
| **Rate limiting (fixed window)** | Counting requests per client in fixed time intervals and refusing those over the limit | Sign-in, registration, carts, checkout |
| **Read model** | A data shape built for reading and showing, not for changing state | `OrderView`, `PaymentView` |
| **Row lock (`SELECT … FOR UPDATE`)** | Locks rows until the transaction ends, so concurrent transactions wait | Carts, products, payments |
| **`SKIP LOCKED`** | Makes a query skip rows other transactions have locked, so several workers share a queue table | The outbox relay |
| **Strangler fig** | Replacing a legacy system piece by piece while the old entry points redirect to the new ones | [Legacy](flows/legacy.md) |
| **TDD** | Test-driven development: write a failing test, make it pass, then refactor | How every change was made |
