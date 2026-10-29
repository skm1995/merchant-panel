# Security

The project is a public demo with open registration, so it assumes that anyone on the
internet can call every endpoint, retry and replay requests, and read whatever the server
reveals. This page lists the threats it defends against, how, and where the code is. A full
security review on 2026-10-27 shaped most of the controls marked as added then.

## Threats and controls

| Threat | Control | Where |
|---|---|---|
| A stranger registers and edits the shop | Registration gives no panel access; an operator grants merchant access, checked in the database on every request | [Authentication](flows/authentication.md), [ADR 0010](decisions/0010-merchant-access-granted-by-an-operator.md) |
| A stranger reads the production logs, with request paths and ids | Kibana is reachable only through Caddy behind the demo tools' password; Elasticsearch listens only on the Compose network; logs never contain request bodies, passwords, or tokens | [ADR 0024](decisions/0024-log-search-in-production.md), [Observability](operations/observability.md) |
| Anyone with the public demo account overloads the server or fills its database with simulations | One simulation at a time with a five-minute cool-down, held by a Redis key with an expiry; a fixed number of buyers and seconds; each run removes the orders and carts the finished runs left | [Black Friday](flows/black-friday.md) |
| Someone skips the rate limits with the simulator's key | The key is random per deployment, required in production instead of falling back to the development value, known only to PHP and the simulator, compared in constant time, accepted only from addresses inside the server's networks, redacted from the access logs, and only lifts the rate limits and allows the simulation's order keys | [`LoadTestKey`](../backend/src/Shared/Http/LoadTestKey.php) |
| A customer's order is removed with the generated history or the simulations | Checkout refuses the idempotency-key prefixes those orders are removed by, `history:` always and `simulation:` without the simulator's key | [Checkout](flows/checkout.md) |
| Exhausting PHP workers or memory with huge request bodies | Caddy answers 413 to bodies over 1 MB before PHP reads them | [`backend.caddy`](../docker/caddy/backend.caddy) |
| A compromised log shipper controls the server | Vector is the only container given the Docker socket, read-only, to read container logs | [`compose.prod.yaml`](../compose.prod.yaml) |
| Someone who saw an order id cancels or pays for the order | Guest order requests need an HMAC-derived access token; a wrong token looks like an unknown order | [ADR 0009](decisions/0009-hmac-order-access-tokens.md) |
| Cross-site request forgery | CSRF token in a custom header for every state change, compared with `hash_equals`; `SameSite=Lax` cookie | [`CsrfMiddleware`](../backend/src/Shared/Http/Middleware/CsrfMiddleware.php) |
| Session theft or fixation | HttpOnly, Secure, `SameSite=Lax` cookie; strict session ids; the id is regenerated and a new CSRF token issued on sign-in | [`Session`](../backend/src/Shared/Http/Session.php), [`CurrentUser`](../backend/src/Auth/Infrastructure/Session/CurrentUser.php) |
| Password guessing | Rate limits on sign-in and registration per client address, before any password check; bcrypt hashes | [`RateLimitMiddleware`](../backend/src/Shared/Http/Middleware/RateLimitMiddleware.php) |
| Learning which accounts exist | Registration answers the same for new and existing addresses and notifies the owner; sign-in takes the same time for unknown addresses | [Registration](flows/registration.md) |
| Passwords that bcrypt cuts or rejects | Registration refuses passwords over 72 bytes or containing NUL | [`UserAuthService`](../backend/src/Auth/Service/UserAuthService.php) |
| Reserving all stock with many carts | Rate limits on cart creation and checkout | [Checkout](flows/checkout.md) |
| SQL injection | Native prepared statements everywhere; the legacy pages that built SQL from input were replaced, and tests prove injected input changes nothing | [Legacy](flows/legacy.md) |
| Cross-site scripting | The React app escapes output; the one server-rendered page escapes every value; a strict Content-Security-Policy allows no inline scripts or styles | [`headers.caddy`](../docker/caddy/headers.caddy) |
| Clickjacking, MIME sniffing, leaking URLs | `frame-ancestors 'none'`, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`; the demo tools' domain gets the same headers except the panel's Content-Security-Policy, because Mailpit and Kibana run their own scripts | [`headers.caddy`](../docker/caddy/headers.caddy), [`prod.Caddyfile`](../docker/caddy/prod.Caddyfile) |
| Forged or replayed payment webhooks | HMAC signatures checked before anything else; QuickPay signatures expire after 5 minutes; event ids are recorded once; amounts are compared | [Payments](flows/payments.md) |
| Double processing of retried requests | Idempotency keys for checkout and payments, optimistic locking for edits | [ADR 0008](decisions/0008-idempotency-keys-and-optimistic-locking.md) |
| Eavesdropping | HTTPS only, with HSTS; certificates renewed automatically | [Production](operations/production.md) |
| Revealing internals in errors | Unexpected errors answer a generic 500 with a request id; details only in the logs; no `Server` or `X-Powered-By` versions | [`UnhandledErrorMiddleware`](../backend/src/Shared/Http/Middleware/UnhandledErrorMiddleware.php) |
| Secrets in the repository or in logs | Secrets live in GitHub and the server's `.env` (mode 600); `compose.prod.yaml` requires every secret, so production never falls back to the public development values in `docker/env/`; gitleaks scans every commit in CI; access logs redact cookies and the API key, order token, and CSRF headers | [CI/CD](operations/ci-cd.md), [`access-log.caddy`](../docker/caddy/access-log.caddy) |
| A compromised container or CI job doing more | Only Caddy publishes ports to the internet, and MySQL listens on the server's loopback only, for SSH tunnels; command-line processes run as `www-data`; the CI token is read-only; development ports listen on localhost only | [System](architecture/system.md) |

## Found and fixed

The review on 2026-10-27 found, among others: open panel access for every confirmed account,
order ids acting as the only key to guest orders, no rate limits, account enumeration through
registration and sign-in timing, a 500 for passwords with NUL characters, sessions lost on
every deployment, missing security headers, and development services listening on all
network interfaces. All of them were fixed with tests; the commit history from that day
records each fix.

Writing this documentation later found that the access logs stored the API key, order tokens,
and CSRF tokens in plain text, because Caddy redacts only cookies and `Authorization` by
default. The access log configuration now replaces those headers with `REDACTED`.

## Reporting a problem

Open an issue in the repository without exploit details, or contact the author through the
links on the [personal site](https://szymonkozlowski.is-a.dev).

## Limitations

- Rate limits count per client address in fixed windows: many addresses can still try many
  passwords, and visitors behind one address share a limit.
- There is no multi-factor authentication, password reset, or account lockout notification.
- Order tokens cannot be revoked one by one.
- Secrets are stored in a plain file on the server rather than a secret manager.
- Dependencies are not scanned for known vulnerabilities in CI.
