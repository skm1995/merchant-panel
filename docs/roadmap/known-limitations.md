# Known limitations

The shortcuts the project takes on purpose, collected from the pages that describe each part,
with their consequences. Items that are planned to change link to the
[roadmap](roadmap.md).

## Product scope

| Limitation | Consequence | Plan |
|---|---|---|
| One shop and one currency per order and report | Every merchant sees and edits the same catalog and orders | [Multi-tenancy](roadmap.md#multi-tenancy) |
| No storefront for customers | Guests use the API directly; only the merchant panel has a UI | [Storefront](roadmap.md#storefront) |
| Simulated payment providers | No real money, refunds, or reconciliation; `refund_required` only records that a refund is due | Out of scope for a demo |
| No password reset, password change, or account deletion | A merchant who forgets the password needs an operator | [Password reset](roadmap.md#password-reset) |
| Merchant access is all or nothing | No read-only or administrator roles | [Roles](roadmap.md#roles-and-permissions) |
| Plain-text emails delivered to Mailpit | The demo sends no real email | Out of scope for a public demo |
| The legacy order report is server-rendered HTML | It is not part of the React panel | Low priority; integrations use the reports API |

## Security

| Limitation | Consequence | Plan |
|---|---|---|
| Rate limits per client address in fixed windows | Many addresses can still try many passwords; visitors behind one address share a limit; a burst at a window's edge can double the rate | [Better rate limits](roadmap.md#better-rate-limits) |
| Many carts can still reserve a large share of stock for 15 minutes | Slowed by the limits, not prevented from many addresses | [Better rate limits](roadmap.md#better-rate-limits) |
| Order tokens are derived from one secret | They cannot be revoked one by one; rotating the secret revokes all | Customer accounts with the [storefront](roadmap.md#storefront) |
| No multi-factor authentication | A stolen password is enough to sign in | [Password reset](roadmap.md#password-reset) adds the email infrastructure it needs |
| Secrets in a plain `.env` file on the server | Anyone with access to the server can read them | Acceptable for one server |
| Dependencies are not scanned in CI | A vulnerable library would go unnoticed | [Dependency scanning](roadmap.md#dependency-scanning) |

## Operations

| Limitation | Consequence | Plan |
|---|---|---|
| One server, no failover, and the provider may reclaim an idle free machine | A server problem takes the demo down | Acceptable for a demo; [rebuild runbook](../operations/runbooks.md#rebuild-the-server-from-scratch) |
| Backups stay on the same server | They do not survive losing the server | [Off-site backups](roadmap.md#off-site-backups) |
| No alerts in production; logs are searchable in Kibana for seven days | Nobody is notified when the demo is down | [Monitoring](roadmap.md#monitoring-and-alerts) |
| Images are built on the server | Deploys take minutes, a failed release leaves the demo down, and rollback means reverting a commit | [Images in CI](roadmap.md#images-built-in-ci) |
| No staging environment | Every green commit goes straight to the demo | [Images in CI](roadmap.md#images-built-in-ci) makes one cheap |

## Code and tests

| Limitation | Consequence | Plan |
|---|---|---|
| The rate limiter connects to Redis on every request | A small cost for routes without a limit | [Performance](roadmap.md#performance) |
| The route table is compiled again for every request | Reflection on every request | [Performance](roadmap.md#performance) |
| A vanished Redis container makes Docker's DNS slow | Requests wait several seconds until Redis returns | Restart policy brings it back; accepted |
| The Black Friday simulation runs on the shop's own server | Its buyers compete with the shop for the CPU, so it shows correctness under concurrency rather than the server's capacity | Acceptable for a demo; `bin/loadtest` with k6 measures latency |
| Few end-to-end tests, no mutation testing | Most behavior is tested below the browser | [Test depth](roadmap.md#test-depth) |
| Frontend pages fetch data individually | No shared cache or retry policy | Fine at the panel's size |
