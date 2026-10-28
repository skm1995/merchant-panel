# Project guide

The repository holds a PHP JSON API in `backend/` and a React application in `frontend/` that talks to the backend only through that API. Read `backend/AGENTS.md` or `frontend/AGENTS.md` before changing code there, and prefer the existing patterns and dependencies of each side.

## Repository layout

- The root holds only what spans both sides: `compose.yaml` and `docker/` (configuration grouped by service), `bin/ci`, `bin/deploy`, and `bin/loadtest`, `.github/`, the API contract in `openapi/`, the k6 scenarios in `loadtest/`, and documentation in `docs/`. Everything owned by one side lives in its directory.
- `openapi/` is the single source of truth for the API: the backend validates real responses against it and the frontend generates its TypeScript types from it. Change the contract first, in `openapi/<feature>.yaml` with shared schemas in `shared.yaml`, and reference every path from `openapi/openapi.yaml`.
- Patterns for recurring problems (API actions, sessions, CQRS, locking, idempotency, webhooks, messaging, caching, reports, legacy code, money, identifiers) are in `docs/patterns.md`; read the matching section before working in that area.

## Workflow

- Follow TDD on both sides, outside in: start a feature with a failing functional test of its behavior from the outside (an API call, a user action), then drop to unit tests where the code has logic with many cases. Make the smallest change that passes, then refactor with the tests green.
- Test behavior, including failure paths, not implementation details or declarations: assert results and state, not which methods were called. A test should survive a refactoring that keeps the behavior.
- Test security and recovery paths explicitly: GET without side effects, rejected CSRF tokens, and behavior when infrastructure such as the broker fails.
- Balance DRY and KISS: give each piece of knowledge one owner, but avoid generic frameworks and indirection that obscure a simple flow.
- Keep `docs/` true to the code in the same commit that changes it: update the architecture, flow, or operations page a change affects, record a new or reversed decision as an ADR in `docs/decisions/`, and move items between the known limitations and the roadmap when a change removes or adds one. Link code by path rather than copying it, so pages stay short and do not drift.
- Name every function and method after the single thing it does; never combine actions in a name such as `getOrCreate()` or hide a second action behind a simple name.

## Infrastructure and configuration

- Optional stacks live behind Compose profiles: `test` for the test infrastructure, `tools` for one-off containers such as the frontend toolchain and Playwright, and `observability` for Elasticsearch, Kibana, and Vector.
- `compose.yaml` serves development and CI; `compose.prod.yaml` is the production server, with the code baked into images and only Caddy publishing ports. Both share the Dockerfiles, `docker/caddy/backend.caddy`, and the variable groups in `docker/env/`.
- Keep `services:` first in `compose.yaml`. Backend services extend `docker/php/compose.yaml`, and each service takes only the variable groups it uses from `docker/env/*.env`. Services that need the schema depend on the `migrate` (or `migrate-test`) service completing.
- Read configuration (DSNs, hosts, credentials, URLs, sender addresses) only from environment variables, with no fallback values in code; local defaults live in `docker/env/*.env` and `.env.example`.
- Never commit `.env` files, IDE settings, or secrets; keep `.env.example` tracked and ignore the rest in `.gitignore`.

## Delivery

- Every push to `master` that passes CI deploys through `bin/deploy`; keep the server reproducible from `docker/server/provision.sh` and GitHub secrets, never from manual changes on it.
- `bin/ci` scans the history for secrets with gitleaks, then verifies both sides against the Compose stack, identically locally and on GitHub; keep service configuration out of the workflow file. Run it without a local `.env` override and push only when it passes.
- `bin/ci` fails when the merged coverage of all suites drops below its minimum (`backend/tools/check-coverage.php`, `frontend/vite.config.ts`). Raise a minimum when coverage grows; never lower one to let a change pass.
- Commit messages follow scoped Conventional Commits: `<type>(<scope>): <description>` with a lowercase scope.
