# Architecture decision records

Each record explains one decision: the situation, what was decided, what else was considered,
and what follows from it. Records are not edited when a decision changes; a new record
supersedes the old one, so the history of the reasoning stays visible.

| No. | Decision | Status |
|---|---|---|
| [0001](0001-build-without-a-framework.md) | Build without a framework | Accepted |
| [0002](0002-organize-by-feature-with-enforced-layers.md) | Organize code by feature, with layers enforced by Deptrac | Accepted |
| [0003](0003-discover-services-routes-and-messages-from-attributes.md) | Discover services, routes, and messages from attributes | Accepted |
| [0004](0004-contract-first-api-with-openapi.md) | Contract-first API with OpenAPI | Accepted |
| [0005](0005-session-cookies-not-jwt.md) | Session cookies, not JWT, for the merchant panel | Accepted |
| [0006](0006-transactional-outbox.md) | Publish messages through a transactional outbox | Accepted |
| [0007](0007-prevent-overselling-in-the-database.md) | Prevent overselling with ordered row locks and conditional updates | Accepted |
| [0008](0008-idempotency-keys-and-optimistic-locking.md) | Idempotency keys for retries, optimistic locking for edits | Accepted |
| [0009](0009-hmac-order-access-tokens.md) | HMAC-derived access tokens for guest orders | Accepted |
| [0010](0010-merchant-access-granted-by-an-operator.md) | Merchant access granted by an operator | Accepted |
| [0011](0011-caddy-in-front-of-php-fpm.md) | Caddy in front of PHP-FPM | Accepted |
| [0012](0012-redis-for-cache-sessions-and-rate-limits.md) | Redis for the cache, sessions, and rate limits | Accepted |
| [0013](0013-separate-backend-and-frontend-in-one-repository.md) | Separate backend and frontend in one repository | Accepted |
| [0014](0014-one-server-deployed-from-ci.md) | One server, deployed from CI with Docker Compose | Accepted |
| [0015](0015-mailpit-and-vector-for-local-tooling.md) | Mailpit and Vector for mail and log collection | Accepted |
