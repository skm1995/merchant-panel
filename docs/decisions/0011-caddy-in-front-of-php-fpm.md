# 0011. Caddy in front of PHP-FPM

Accepted, 2026-10-26 for Caddy; PHP-FPM since 2026-10-27. Supersedes nginx and Apache.

## Context

The project first used nginx as the reverse proxy and Apache with mod_php inside the PHP
container. Production needs HTTPS certificates, and the same proxy configuration should be
tested in CI that runs in production.

## Decision

Use Caddy everywhere, in development, tests, and production, with the backend routes in one
shared file, [`backend.caddy`](../../docker/caddy/backend.caddy). Caddy obtains and renews
certificates itself and sends `/api/` and `/legacy/` to PHP-FPM over FastCGI, so the PHP
container runs no web server of its own.

## Alternatives

- **nginx with certbot.** More common in companies, but certificate renewal needs a cron job
  and a reload, and a two-step bootstrap for the first certificate.
- **Apache with mod_php behind the proxy.** Worked, but added a second web server to
  configure and secure for no benefit.

## Consequences

- One proxy configuration is exercised by the end-to-end tests and runs in production.
- HTTPS works with a domain name and no further setup.
- Security headers and the Content-Security-Policy live in one file,
  [`headers.caddy`](../../docker/caddy/headers.caddy).
