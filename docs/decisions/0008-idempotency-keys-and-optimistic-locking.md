# 0008. Idempotency keys for retries, optimistic locking for edits

Accepted, 2026-10-25.

## Context

Clients retry requests after timeouts, so a retried checkout or payment must not run twice.
Two people may edit the same order or product, and the second must not overwrite the first
without noticing.

## Decision

- **Retries:** checkout and payment start require an `Idempotency-Key` header. The key is
  stored with a unique index; a repeat with the same key and target returns the original
  result, and a repeat for another target answers 422.
- **Concurrent edits:** orders and products carry a version, exposed as an `ETag`. Changes
  require it in `If-Match` (428 without it), and the update applies only to that version
  (412 when someone changed it first).

## Alternatives

- **Pessimistic locks held between reads and writes.** Impossible across HTTP requests.
- **Last write wins.** Simple, but silently loses changes.

## Consequences

- Retries are safe for clients, including the provider's webhook retries, which use event ids
  for the same purpose.
- Clients must keep and send the `ETag`; the panel does this for product edits.
