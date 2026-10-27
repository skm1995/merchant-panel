# 0015. Mailpit and Vector for mail and log collection

Accepted, 2026-10-26. Supersedes MailHog and Filebeat.

## Context

The project first used MailHog to catch email and Filebeat to ship logs to Elasticsearch.
MailHog has not been maintained since 2020 and ships no ARM image, which the production server
needs. Filebeat reads log files from the host, which Docker Desktop keeps inside its virtual
machine, so it collected nothing on a developer's machine.

## Decision

Use [Mailpit](https://mailpit.axllent.org), MailHog's maintained successor with the same ports
and multi-architecture images, and [Vector](https://vector.dev), which reads container logs
through the Docker API and sends them to Elasticsearch.

## Alternatives

- **A real SMTP provider for the demo.** Would deliver to real inboxes, which a public demo
  with open registration should not do.
- **Fluent Bit or Logstash.** Capable, but Vector works the same on Linux, Docker Desktop, and
  CI with one small configuration.

## Consequences

- The demo shows confirmation emails in Mailpit behind a password.
- Log collection works on every platform the project runs on.
