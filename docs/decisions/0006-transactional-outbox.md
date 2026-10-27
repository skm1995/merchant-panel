# 0006. Publish messages through a transactional outbox

Accepted, 2026-10-25.

## Context

Registration must send an email, checkout must expire unpaid orders later, and product
changes must invalidate the cache. Publishing to RabbitMQ inside a request either loses the
message when the broker is down or sends it for data that is then rolled back.

## Decision

Store each message in the `outbox_messages` table in the same transaction as the data it is
about. A separate [relay](../../backend/src/Shared/Messaging/Outbox/OutboxRelay.php) leases
rows with `SELECT … FOR UPDATE SKIP LOCKED`, publishes them, and deletes them; messages due
later are published with a delay. Handlers are idempotent, because delivery is at least once.

## Alternatives

- **Publish directly from the request.** Simple, but loses or duplicates work on failures.
- **Distributed transactions.** Not supported by RabbitMQ in a practical way.
- **Change data capture from the binlog.** Robust, but much more infrastructure.

## Consequences

- A broker outage delays messages but never loses them, and a rolled-back request sends
  nothing.
- Several relays can run side by side, and a crashed relay's messages are published again
  after their lease.
- The request id is stored with the message, so logs connect a request with the work it
  caused.
