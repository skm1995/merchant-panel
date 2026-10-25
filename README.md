# Vanilla PHP Playground

## Local development

Copy `.env.example` to `.env`, then start the application and services.
Application configuration (database, RabbitMQ, mailer and public URLs) is read
only from environment variables; local defaults live in `compose.yaml` and
`.env.example`, and the application fails fast when a required variable is
missing.

```sh
docker compose up --build -d
docker compose exec php composer migrate
```

Set `SESSION_COOKIE_SECURE=true` when the site is served over HTTPS, including
when TLS terminates at a reverse proxy.

The application runs at `http://localhost:8080`. MailHog is available at
`http://localhost:8025`, and RabbitMQ management is available at
`http://localhost:15672`.

## Failed confirmation emails

After the worker exhausts its configured retries, messages are stored in the
`emails_failed` RabbitMQ queue. Inspect that queue and the worker logs before
retrying. To requeue all available failed email messages, run:

```sh
docker compose exec php php bin/retry-failed-emails.php
```

The command acknowledges a failed message only after it has been dispatched to
the normal email queue. If dispatch fails, that message remains in the failed
queue. The command retries every message currently available in that queue.
Messages that can no longer be decoded, for example after a message class was
renamed, are left in the failure queue and reported so they can be inspected
in the RabbitMQ management UI.

An unverified account can request at most five resend attempts in each
24-hour window. The initial confirmation email is not counted as a resend.

## Verification

Run the complete CI verification locally before pushing. GitHub Actions runs
the same script against the same Docker Compose stack:

```sh
bin/ci
```
