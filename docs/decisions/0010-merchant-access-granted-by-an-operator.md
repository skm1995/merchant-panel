# 0010. Merchant access granted by an operator

Accepted, 2026-10-27.

## Context

Registration is open, and at first every confirmed account could use the merchant panel: read
all orders and reports and change any price. A security review classified this as the most
serious finding.

## Decision

Add `users.merchant_since`. Registration and confirmation give no access; an operator grants
it with `bin/grant-merchant.php`, and `bin/create-merchant.php` creates a confirmed merchant
directly, which deployment uses for the shared demo account. Panel routes require
`#[RequireMerchant]`, which reads the role from the database on every request.

## Alternatives

- **Invitations.** Better for a real team, but more screens and expiring links.
- **Separate shops per merchant (multi-tenancy).** Closest to a SaaS platform, but a rewrite
  of the data model.
- **Storing the role in the session.** Saves a query per request, but a revoked merchant
  would keep access until signing out.

## Consequences

- Strangers who register cannot see or change anything in the panel.
- Access changes apply at once.
- The demo works through a shared account whose credentials are public on purpose.
