# 0026. Alerts from a scheduled GitHub workflow

Accepted, 2026-10-29.

## Context

Nobody was told when the demo went down or started failing; the logs were searchable in
Kibana ([0024](0024-log-search-in-production.md)), but someone had to look. The roadmap
proposed an external uptime service and a Kibana rule that sends email. Kibana's free license
offers no email connector for its rules, and an external service is one more account to
keep. The repository is public, so GitHub Actions minutes cost nothing, and GitHub already
emails the owner when a scheduled workflow fails.

## Decision

[`bin/monitor`](../../bin/monitor) checks the demo from the outside: the panel and the API
answer, and the logs of the last 20 minutes hold no record at Monolog's ERROR level, which
the application writes for unhandled exceptions and for messages the worker failed to handle.
It reads the logs through the Elasticsearch proxy of Kibana's Dev Tools console, behind the
demo tools' password that the deploy already holds as a secret. A scheduled workflow,
[`monitor.yaml`](../../.github/workflows/monitor.yaml), runs it every 15 minutes, and a failed
run is the alert.

## Alternatives

- **An external uptime service.** Watches from several places and every minute, but sees
  only whether the site answers, not the errors in the logs, and needs its own account.
- **A Kibana rule writing to an index or the server log.** Free, but notifies nobody.
- **A check on the server, with cron.** Sees everything, but cannot alert anyone when the
  server itself is down, and the server sends no real email.
- **A paid Elastic license for email.** Not worth it for a demo.

## Consequences

- An outage or an error reaches the owner's inbox within about 15 minutes, with the failing
  check, the error messages, and their request ids in the run's log.
- GitHub may start scheduled runs late, so the error window overlaps the schedule and an
  error can alert twice.
- The check retries for up to five minutes, so a deployment's restarts do not alert.
- Kibana serves its console proxy only to requests marked as its own; if a Kibana upgrade
  changes that, the monitor fails and says the logs cannot be searched, rather than staying
  silent.
- GitHub disables the schedule after 60 days without a commit; the
  [runbook](../operations/runbooks.md#when-the-monitor-fails) says how to re-enable it.
