# Backend guide

A pure PHP JSON API without a framework or ORM. The root `AGENTS.md` applies as well.

## Testing

- Give each production class its own `<ClassName>Test` in the mirrored namespace under `tests/Unit/`. Unit tests build classes directly with test doubles; test controllers through `Tests\Unit\Support\HttpHarness`, which dispatches through the real router.
- Keep unit and functional suites separate. Functional tests run against dedicated test infrastructure (database, broker queues, worker), apply migrations, clean up their data, and use unique test data.
- Exercise HTTP in functional tests through nginx and the web server with an HTTP client: `ApiClient` for sessionless routes and `SessionApiClient` for session routes, which keeps the cookie and sends the CSRF header. Never include the front controller or overwrite superglobals. Test repositories against the real test database instead of mocking PDO. Browser flows are covered by the Playwright tests in `frontend/e2e/`.
- Functional tests validate every real API response, including problem details, against `../openapi/`.

## Architecture

- Organize `src/` by feature (`Auth/`, `Catalog/`, …) with singular directory names and matching namespaces; use `Shared/` only for code several features use. No top-level type folders such as `src/Controller/`.
- Inside a feature, put exceptions in `Exception/`, enums in `Enum/`, request DTOs in `Request/`, and response DTOs in `Response/`, even for a single file; add other layer directories when they help. Remove directories that become empty.
- Layers: controllers translate HTTP into one application call and a response and map domain exceptions to status codes; application services implement use cases with typed arguments and know nothing about HTTP, URLs, or views; entities and value objects hold domain rules; repositories own persistence; infrastructure adapters integrate external systems.
- Put cross-cutting HTTP policy in middleware under `src/Shared/Http/Middleware/`, one policy per class (`MiddlewareInterface::process()`), composed per route by the router from the route's path and attributes (`#[UsesSession]`, `#[RequireApiKey]`, `#[MapException]`). Controllers receive shared components such as `Responder` by injection; prefer composition over base classes.
- Routes are `#[Route]` attributes on public controller methods that take no argument or a single `Request`; controllers in `src/*/Controller` are discovered automatically. Never keep hand-written registries of controllers, routes, messages, handlers, or services.
- Build services through the container (`ContainerFactory::create()`), never with `new` in entry points. It autowires constructors; binds an application interface with one implementation automatically; uses `#[DefaultImplementation]` among several and `#[ServiceFactory]` methods for vendor or custom-built services; injects `#[Env('VARIABLE')]` and `#[Autowire(Class::class)]` parameters. `bin/container-debug.php` lists the bindings.
- Report expected failures with specific domain exceptions and catch only what a flow handles. Avoid boolean flag parameters; use separately named methods.
- Hydrate query results into readonly `#[HydratedRecord]` DTOs through `RecordFetcher`; entity records implement `EntityRecord::toEntity()` and array-shaped DTOs implement `Arrayable`. Never pass associative row arrays between methods.

## Code style

- PSR-12 for the PHP version in `composer.json`, PHPStan level 8.
- Use the pipe operator (`|>`) for left-to-right transformations with first-class callables; do not force it or wrap a single expression in a closure just to pipe it.
- Omit redundant parentheses around `new` when accessing a member. Give class constants explicit types; prefer backed enums for finite sets. Declare a class `final readonly` when all its properties are readonly.
- Method names: `from*()` named constructors return their own class and `parse()` returns another type; never hide a second action such as starting a session inside `get()`; repositories `add()` new entities, `find*()` returns `null` when missing, and `*ForUpdate()` locks rows; boolean methods read as questions (`isValid()`, `passwordMatches()`).
- Never use empty string literals, and put string values in constants rather than inline literals (enum methods may use literals). Prefer readable `/` paths over assembled segments.

## Dependencies and data

- Manage dependencies with Composer and commit the lockfile. Declare every PHP extension the code or its dependencies need as `ext-*` (for example `bcmath` for moneyphp, `amqp` for Messenger).
- Change the schema only with Phinx migrations: descriptive names, reversible where practical, never edited after being applied, dated after the latest committed one, columns `'null' => false` unless `NULL` means something, invariants enforced by constraints.
