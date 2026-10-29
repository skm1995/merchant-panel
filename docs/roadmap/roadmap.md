# Roadmap

What would come next, ordered by priority. Each item states the problem, the proposed
solution, and the effort: S is a day or less, M a few days, L a week or more. Items come from
the [known limitations](known-limitations.md); the most important ones have a sketch of the
solution.

| Priority | Item | Effort |
|---|---|---|
| 2 | [Staging environment](#staging-environment) | M |
| 2 | [Better rate limits](#better-rate-limits) | S |
| 2 | [Published API reference](#published-api-reference) | S |
| 3 | [Roles and permissions](#roles-and-permissions) | M |
| 3 | [Storefront](#storefront) | L |
| 3 | [Multi-tenancy](#multi-tenancy) | L |
| 3 | [Performance](#performance) | S |
| 3 | [Test depth](#test-depth) | M |

## Staging environment

**Problem:** every green commit goes straight to the demo.

**Sketch:** the images CI builds for every commit
([0029](../decisions/0029-images-built-in-ci.md)) can run a second Compose project on the
same server under `staging.<domain>`, with its own database and secrets. The deploy job would
start the commit there first, run the end-to-end tests against it, and only then pull the same
images for the demo. The same registry would let `bin/ci` on GitHub reuse its test images
instead of building them on every run.

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
