# Local development

Everything runs in Docker; the host needs only Docker with Compose v2 and Git. On Windows the
project runs in WSL 2 with Docker Desktop.

## Start

```sh
docker compose up --build -d
```

The `migrate` container applies the database migrations first; the PHP containers start only
after it succeeded. Configuration comes from [`docker/env/*.env`](../../docker/env), which
hold safe local defaults. To override a value, copy [`.env.example`](../../.env.example) to
`.env`; that file is ignored by Git.

| Address | What |
|---|---|
| `http://localhost:8080` | The merchant panel, with hot reload, and the API under `/api/` |
| `http://localhost:8025` | Mailpit: every email the application sends |
| `http://localhost:15672` | RabbitMQ management (user and password `app`) |
| `127.0.0.1:3306`, `:6379` | MySQL (`app` / `app`) and Redis, for database tools |

Development ports listen on `127.0.0.1` only, so these development credentials are not
reachable from the local network.

To use the panel, register at `/register`, open the confirmation email in Mailpit, and grant
the account merchant access:

```sh
docker compose exec php php bin/grant-merchant.php you@example.com
```

## Everyday commands

| Task | Command |
|---|---|
| Every check that CI runs | `bin/ci` |
| PHP unit tests | `docker compose --profile test run --rm --no-deps php-test composer test-unit` |
| PHP functional tests (needs the test stack: `docker compose --profile test up -d --wait`) | `docker compose --profile test run --rm --no-deps php-test composer test-functional` |
| One PHP test | `docker compose --profile test run --rm --no-deps php-test vendor/bin/phpunit --filter NameOfTest` |
| PHPStan, Deptrac, code style | `… php-test composer analyse`, `composer deptrac`, `composer cs-check` (or `cs-fix`) |
| Frontend tests | `docker compose --profile tools run --rm frontend-test npm test` |
| Frontend type check, lint, format | `… frontend-test npm run typecheck`, `npm run lint`, `npm run format` |
| Regenerate the API types after changing `openapi/` | `… frontend-test npm run api:types` |
| End-to-end tests (after `bin/ci` built the app) | `docker compose --profile tools run --rm e2e npx playwright test` |
| Load test | `bin/loadtest` |
| See the container's service bindings | `docker compose exec php php bin/container-debug.php` |

## Try the API

The API is described in [`openapi/`](../../openapi/openapi.yaml);
`docker compose --profile test run --rm --no-deps php-test composer openapi-bundle` writes a
single-file `build/openapi.yaml` for tools that need one. A guest checkout from the command
line:

```sh
api=http://localhost:8080/api/v1
curl -s "$api/products?limit=5"
cart=$(curl -s -X POST "$api/carts" | sed -E 's/.*"id":"([^"]+)".*/\1/')
curl -s -X PUT "$api/carts/$cart/items/1" -H 'Content-Type: application/json' -d '{"quantity": 2}'
curl -s -D - -X POST "$api/carts/$cart/checkout" -H "Idempotency-Key: $(uuidgen)"
```

The checkout response carries the order's `X-Order-Token`, which reading, cancelling, and
paying for the order need. The reports API takes the development key:

```sh
curl -H 'X-Api-Key: local-reports-api-key' "$api/reports/daily-revenue?from=2026-10-01&to=2026-10-31"
```

## Database changes

The schema changes only through Phinx migrations in
[`backend/database/migrations`](../../backend/database/migrations). Name a new file after the
latest one with a later date, keep it reversible where practical, and never edit a migration
that was already applied. `docker compose up -d migrate` applies new migrations to the
development database; the test stack applies them through `migrate-test`.

## Debugging

The development PHP image includes Xdebug. Map the repository root to `/app` in the IDE, as
the containers mount the repository there and work in `/app/backend`. Every response carries
`X-Request-Id`; `docker compose logs php worker | grep <id>` shows everything that request
caused, or use [Kibana](observability.md).

## Limitations

- The first `docker compose up --build` downloads and builds several images and takes a few
  minutes.
