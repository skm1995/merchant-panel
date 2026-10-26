# Project guide

The repository holds a PHP JSON API in `backend/` and a React application in `frontend/` that talks to the backend only through that API. Read `backend/AGENTS.md` or `frontend/AGENTS.md` before changing code there, and prefer the existing patterns and dependencies of each side.

## Repository layout

- The root holds only what spans both sides: `compose.yaml` and `docker/` (configuration grouped by service), `bin/ci`, `.github/`, the API contract in `openapi/`, and documentation in `docs/`. Everything owned by one side lives in its directory.
- `openapi/` is the single source of truth for the API: the backend validates real responses against it and the frontend generates its TypeScript types from it. Change the contract first, in `openapi/<feature>.yaml` with shared schemas in `shared.yaml`, and reference every path from `openapi/openapi.yaml`.
- Patterns for recurring problems (API actions, sessions, CQRS, locking, idempotency, webhooks, messaging, caching, reports, legacy code, money, identifiers) are in `docs/patterns.md`; read the matching section before working in that area.

## Workflow

- Follow TDD on both sides: write a failing test, make the smallest change that passes it, then refactor with the tests green. Test behavior, including failure paths, not implementation details or declarations.
- Test security and recovery paths explicitly: GET without side effects, rejected CSRF tokens, and behavior when infrastructure such as the broker fails.
- Balance DRY and KISS: give each piece of knowledge one owner, but avoid generic frameworks and indirection that obscure a simple flow.
- Name every function and method after the single thing it does; never combine actions in a name such as `getOrCreate()` or hide a second action behind a simple name.

## Infrastructure and configuration

- Optional stacks live behind Compose profiles: `test` for the test infrastructure, `tools` for one-off containers such as the frontend toolchain and Playwright, and `observability` for Elasticsearch, Kibana, and Vector.
- Keep `services:` first in `compose.yaml`. Backend services extend `docker/php/compose.yaml`, and each service takes only the variable groups it uses from `docker/env/*.env`. Services that need the schema depend on the `migrate` (or `migrate-test`) service completing.
- Read configuration (DSNs, hosts, credentials, URLs, sender addresses) only from environment variables, with no fallback values in code; local defaults live in `docker/env/*.env` and `.env.example`.
- Never commit `.env` files, IDE settings, or secrets; keep `.env.example` tracked and ignore the rest in `.gitignore`.

## Delivery

- `bin/ci` verifies both sides against the Compose stack, identically locally and on GitHub; keep service configuration out of the workflow file. Run it without a local `.env` override and push only when it passes.
- Commit messages follow scoped Conventional Commits: `<type>(<scope>): <description>` with a lowercase scope.
