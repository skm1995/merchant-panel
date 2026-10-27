# 0009. HMAC-derived access tokens for guest orders

Accepted, 2026-10-27.

## Context

Guests check out without an account. At first the order id was the only key to an order, but
the id appears in the merchant panel, in logs, and in URLs, so anyone who saw it could cancel
or pay for the order.

## Decision

Checkout returns an access token in the `X-Order-Token` header, and reading, cancelling, and
paying for the order and reading its payments require it.
[`OrderAccess`](../../backend/src/Order/Service/OrderAccess.php) derives the token as an
HMAC-SHA256 of the order id with `ORDER_ACCESS_SECRET` and compares with `hash_equals`; a wrong
token answers 404, so it does not confirm that the order exists.

## Alternatives

- **A random token stored per order.** Can be revoked individually, but a retried checkout
  could not return it again without storing it in plain text.
- **Customer accounts.** The right answer for a real shop, but a much larger feature.

## Consequences

- No storage and no migration; a retried checkout returns the same token.
- Tokens cannot be revoked one by one; rotating the secret revokes all of them.
- The token travels in a header, never in a URL, so it does not end up in access logs.
