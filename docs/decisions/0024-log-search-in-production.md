# 0024. Log search in production, under the demo's tools domain

Accepted, 2026-10-28.

## Context

Production logs were only in Docker's rotated files, read with `docker compose logs` and
`grep`. The server has 24 GB of memory, of which the application uses less than half, and the
demo is meant to show how the shop behaves under load, which needs searchable logs and charts.
Elasticsearch, Kibana, and Vector already ran locally in the `observability` profile.

## Decision

Run the same three services in production. Vector ships every container's logs to
Elasticsearch, which keeps daily indices for seven days on one node without replicas. Kibana
serves itself under `/kibana` on the mail domain, behind the same password as Mailpit, so no
new domain, certificate, or secret is needed. One idempotent script,
[`docker/elk/setup.sh`](../../docker/elk/setup.sh), prepares retention, the data view, and the
dashboards after every deploy and in development alike.

## Alternatives

- **A separate `logs` subdomain.** Cleaner, but needs another is-a.dev registration and a
  certificate; moving Kibana there later is a change to the Caddyfile.
- **Kibana on the application domain.** Its own scripts and styles would conflict with the
  panel's strict Content-Security-Policy.
- **A hosted log service.** Nothing to run, but the free tiers keep little and the logs would
  leave the server.
- **Grafana with Loki.** Lighter, but the job offer this project targets names the ELK stack.

## Consequences

- Logs of the API, the worker, and the commands are searchable and charted on the
  [Black Friday dashboard](../operations/observability.md#on-the-server).
- Elasticsearch and Kibana take about 4.5 GB of memory and up to a few gigabytes of disk for
  a week of logs.
- Vector reads the Docker socket, read-only, to collect the logs; it is the only container
  with access to it.
- There are still no alerts; [0026](0026-alerts-from-a-scheduled-github-workflow.md) adds them.
