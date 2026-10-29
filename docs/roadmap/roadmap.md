# Roadmap

What would come next, ordered by priority. Each item states the problem, the proposed
solution, and the effort: S is a day or less, M a few days, L a week or more. Items come from
the [known limitations](known-limitations.md); the most important ones have a sketch of the
solution.

| Priority | Item | Effort |
|---|---|---|
| 1 | [Password reset](#password-reset) | M |
| 1 | [Off-site backups](#off-site-backups) | S |
| 2 | [Images built in CI](#images-built-in-ci) | M |
| 2 | [Better rate limits](#better-rate-limits) | S |
| 2 | [Published API reference](#published-api-reference) | S |
| 3 | [Roles and permissions](#roles-and-permissions) | M |
| 3 | [Storefront](#storefront) | L |
| 3 | [Multi-tenancy](#multi-tenancy) | L |
| 3 | [Performance](#performance) | S |
| 3 | [Test depth](#test-depth) | M |

## Password reset

**Problem:** a merchant who forgets the password depends on an operator.

**Sketch:**

1. `POST /api/v1/password-resets {email}` answers 202 for every address, like registration,
   and is rate-limited.
2. For an existing account, store the SHA-256 hash of a random token with a 1-hour expiry, and
   send the link through the outbox, reusing the confirmation email's machinery.
3. `POST /api/v1/password-resets/confirm {token, password}` applies the password rules,
   stores the new hash, deletes every reset token of the account, and ends its other
   sessions, which needs an index of session ids per user in Redis.
4. The React panel gets "Forgot password" and "Set a new password" pages; tests follow the
   registration flow's: unit, functional through Mailpit, and one end-to-end journey.

## Off-site backups

**Problem:** the nightly dumps stay on the server they protect.

**Sketch:** after `backup-database.sh` writes a dump, upload it to Oracle Cloud Object
Storage, which the Always Free tier includes, with credentials limited to that bucket and a
lifecycle rule that deletes objects after 30 days. A monthly runbook step restores the latest
dump into a scratch database to prove the backups work.

## Images built in CI

**Problem:** building on the server makes deploys slow and rollbacks manual.

**Sketch:** build the PHP and Caddy images for ARM in GitHub Actions, push them to the GitHub
Container Registry tagged with the commit, and let `bin/deploy` pull a tag instead of
building. Rolling back becomes deploying the previous tag, and a staging stack on the same
server can run the next tag before it reaches the demo. The same registry would let `bin/ci`
on GitHub reuse the test images instead of building them on every run, which takes about
half of its four minutes now; `docker compose build` cannot use the Actions cache directly.

## Better rate limits

**Problem:** fixed windows per address allow bursts at window edges and treat all visitors
behind one address as one.

**Sketch:** a sliding window built from two Redis counters, plus a second limit per account
for sign-in, so attempts against one account are limited across addresses, and a limit on
open reservations per cart owner.

## Published API reference

**Sketch:** render the OpenAPI contract with Redoc into a static page on the personal site or
GitHub Pages, published by CI when `openapi/` changes.

## Roles and permissions

**Sketch:** replace `merchant_since` with a role column (`viewer`, `merchant`, `owner`) and
the `#[RequireMerchant]` attribute with `#[RequireRole]`, letting owners grant roles from the
panel instead of the command line.

## Storefront

**Sketch:** a customer-facing React app on its own domain using the existing catalog, cart,
checkout, and payment API, with optional customer accounts that would make order tokens a
fallback for guests only.

## Multi-tenancy

**Sketch:** a `shops` table and a shop id on products, orders, carts, and users, set from the
signed-in merchant and from the storefront's domain, enforced in every repository query and
tested by a suite that tries to read another shop's data through every endpoint.

## Performance

**Sketch:** connect to Redis lazily, only when a cache or limiter call needs it, and cache the
compiled route table next to the service discovery cache.

## Test depth

**Sketch:** more end-to-end journeys (checkout and payment through the API in the browser
tests), and mutation testing with Infection on the domain and application layers, which
shows whether covered code is also checked by an assertion.
