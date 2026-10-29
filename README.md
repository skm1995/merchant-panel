# Merchant Panel

[![CI](https://github.com/skm1995/merchant-panel/actions/workflows/ci.yaml/badge.svg)](https://github.com/skm1995/merchant-panel/actions/workflows/ci.yaml)

An e-commerce backend with a merchant panel, built without a framework: a PHP 8.5 JSON API,
a React panel, asynchronous messaging through a transactional outbox and RabbitMQ, and a CI
pipeline that deploys every commit that passes its tests.

## Live demo

**[demo.szymonkozlowski.is-a.dev](https://demo.szymonkozlowski.is-a.dev)**: sign in to the
merchant panel with the shared demo account `demo@example.com` / `demo-merchant-2026`. Other
visitors use it too, so its data may change.

## Highlights

- **Checkout under concurrency:** stock reserved with ordered row locks and conditional
  updates, idempotent retries, and unpaid reservations that expire on their own; a k6 load
  test proves nothing is oversold.
- **Reliable messaging:** emails, expiries, and cache invalidation go through a transactional
  outbox, so a broker outage never loses work, and a request id follows each request into the
  worker's logs.
- **Payments:** two simulated providers whose signed webhooks are applied exactly once, even
  when delivered concurrently.
- **Security:** session cookies with CSRF protection, merchant-only panel access, rate limits,
  access tokens for guest orders, a strict Content-Security-Policy, and secret scanning in CI.
- **Framework-free foundations:** dependency injection, routing, middleware, and messaging
  discovered from attributes, with layers enforced by Deptrac.
- **Contract-first:** OpenAPI is the single source of truth, validated against real responses
  and compiled into the panel's TypeScript types.
- **Tested and delivered:** unit, functional, end-to-end, and load tests, PHPStan level 8,
  and a deploy to Oracle Cloud after every green commit.

## Quick start

Requires Docker with Compose v2.

```sh
docker compose up --build -d   # the panel and API on http://localhost:8080, Mailpit on :8025
bin/ci                         # every check that runs on GitHub
```

[Local development](docs/operations/local-development.md) lists the other addresses and
commands.

## Documentation

The [documentation](docs/README.md) explains how the system works and why it is built this
way:

- [Overview](docs/overview.md) and [architecture](docs/architecture/system.md)
- [Business flows](docs/flows/checkout.md): registration, authentication, password reset, checkout, payments,
  the panel, and replacing legacy code
- [Architecture decisions](docs/decisions/README.md) with the alternatives considered
- [Operations](docs/operations/ci-cd.md), [security](docs/security.md), and the
  [roadmap](docs/roadmap/roadmap.md)

## Built with

PHP 8.5, Symfony Messenger and Mailer, MySQL 8.4, RabbitMQ, Redis, React 19, TypeScript,
Vite, Caddy, Docker Compose, GitHub Actions, OpenAPI, PHPUnit, Vitest, Playwright, and k6.

Tooling: PhpStorm, Docker, GitHub Actions, Claude Code.

## Repository layout

| Path | Contents |
|---|---|
| [`backend/`](backend) | PHP JSON API: `src/` by feature, tests, migrations, and commands |
| [`frontend/`](frontend) | React merchant panel and its end-to-end tests |
| [`openapi/`](openapi) | API contract shared by both sides |
| [`docker/`](docker), [`compose.yaml`](compose.yaml), [`compose.prod.yaml`](compose.prod.yaml) | Containers for development, tests, and production |
| [`bin/`](bin) | `ci`, `deploy`, and `loadtest` |
| [`loadtest/`](loadtest) | The "Black Friday" k6 scenario |
| [`docs/`](docs) | Documentation |
