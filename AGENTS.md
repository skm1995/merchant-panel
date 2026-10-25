# Project guide

Use these rules when changing this project, and prefer its existing patterns and dependencies. Before working on API actions, forms and sessions, registration, CQRS, transactions and locking, idempotency, optimistic locking, payment providers and webhooks, messaging, caching, reports, legacy code, money, or identifiers, read the matching section of `docs/patterns.md`.

## Workflow and testing

- Follow TDD: write a failing test, make the smallest change that passes it, then refactor with the tests green. Test behavior, including failure paths, not declarations such as constant or enum values.
- Give each production class its own `<ClassName>Test` in the mirrored namespace under `tests/Unit/`. Unit tests build classes directly with test doubles; test controllers through `Tests\Unit\Support\HttpHarness`, which dispatches through the real router.
- Keep unit and functional suites separate. Functional tests run against dedicated test infrastructure (database, broker queues, worker), apply migrations, clean up their data, and use unique test data.
- Document every JSON API operation in `openapi/<feature>.yaml` (shared schemas in `shared.yaml`) and reference its paths from `openapi/openapi.yaml`; functional tests validate real responses against it.
- Exercise HTTP in functional tests through the web server with an HTTP client (`HttpBrowser` for browser flows); never include the front controller or overwrite superglobals. Test repositories against the real test database instead of mocking PDO.
- Test security and recovery paths explicitly: GET without side effects, rejected CSRF tokens, and behavior when infrastructure such as the broker fails.

## Architecture

- Organize `src/` by feature (`Auth/`, `Catalog/`, …) with singular directory names and matching namespaces; use `Shared/` only for code several features use. No top-level type folders such as `src/Controller/`.
- Inside a feature, put exceptions in `Exception/`, enums in `Enum/`, request DTOs in `Request/`, and response DTOs in `Response/`, even for a single file; add other layer directories when they help. Remove directories that become empty.
- Layers: controllers translate HTTP into one application call and a response and map domain exceptions to status codes; application services implement use cases with typed arguments and know nothing about HTTP, URLs, or views; entities and value objects hold domain rules; repositories own persistence; infrastructure adapters integrate external systems.
- Put cross-cutting HTTP policy (sessions, CSRF, request parsing, response writing, dispatch) in shared components injected into controllers; prefer composition over base classes.
- Routes are `#[Route]` attributes on public controller methods that take no argument or a single `Request`; controllers in `src/*/Controller` are discovered automatically. Never keep hand-written registries of controllers, routes, messages, handlers, or services.
- Build services through the container (`ContainerFactory::create()`), never with `new` in entry points. It autowires constructors; binds an application interface with one implementation automatically; uses `#[DefaultImplementation]` among several and `#[ServiceFactory]` methods for vendor or custom-built services; injects `#[Env('VARIABLE')]` and `#[Autowire(Class::class)]` parameters. `bin/container-debug.php` lists the bindings.
- Report expected failures with specific domain exceptions and catch only what a flow handles. Avoid boolean flag parameters; use separately named methods.
- Hydrate query results into readonly `#[HydratedRecord]` DTOs through `RecordFetcher`; entity records implement `EntityRecord::toEntity()` and array-shaped DTOs implement `Arrayable`. Never pass associative row arrays between methods.
- Read configuration (DSNs, hosts, credentials, URLs, sender addresses) only from environment variables, with no fallback values in PHP; local defaults live in `compose.yaml` and `.env.example`.
- Balance DRY and KISS: give each piece of knowledge (a session key, route path, algorithm, format) one owning class, but avoid generic frameworks and indirection that obscure a simple flow.

## Code style

- PSR-12 for the PHP version in `composer.json`, PHPStan level 8.
- Use the pipe operator (`|>`) for left-to-right transformations with first-class callables; do not force it or wrap a single expression in a closure just to pipe it.
- Omit redundant parentheses around `new` when accessing a member. Give class constants explicit types; prefer backed enums for finite sets.
- Never use empty string literals, and put string values in constants rather than inline literals (enum methods may use literals). Prefer readable `/` paths over assembled segments.

## Dependencies and data

- Manage dependencies with Composer and commit the lockfile. Declare every PHP extension the code or its dependencies need as `ext-*` (for example `bcmath` for moneyphp, `amqp` for Messenger).
- Group Docker configuration by service, share repeated Compose configuration with YAML anchors, and give each service only the variables it uses.
- Change the schema only with Phinx migrations: descriptive names, reversible where practical, never edited after being applied, dated after the latest committed one, columns `'null' => false` unless `NULL` means something, invariants enforced by constraints.
- Never commit `.env` files or secrets; keep `.env.example` tracked and ignore the rest in `.gitignore`.

## Delivery

- `bin/ci` runs migrations, both test suites, PHPStan, and PHP CS Fixer against the Compose stack, identically locally and on GitHub; keep service configuration out of the workflow file. Run it without a local `.env` override and push only when it passes.
- Commit messages follow scoped Conventional Commits: `<type>(<scope>): <description>` with a lowercase scope.
