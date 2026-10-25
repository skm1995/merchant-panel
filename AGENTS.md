# Project guide

Use these rules when changing this project. Prefer its existing patterns and dependencies; keep new code consistent with the surrounding design.

## Development workflow

- Follow TDD: first write a failing test for the expected behavior, then make the smallest change that passes it, and refactor while keeping tests green.
- Cover application behavior with PHPUnit, including relevant failure paths. Test behavior, not declarations: do not add tests that only restate constant or enum values.
- Give each production class its own `<ClassName>Test` in the mirrored test namespace; do not group several classes in one test class.
- Make mocks of third-party APIs follow their real contract (for example, an AMQP receiver's `get()` returns at most one message per call), and verify recovery commands such as failed-message retries against the real local broker.
- Keep unit and functional tests in separate suites. Run both suites before considering a change complete.
- Functional tests must use a dedicated test database, apply migrations first, and clean up their data. Never run them against the development database.
- Exercise HTTP behavior in the functional suite through the configured web server with an HTTP client (Symfony `HttpBrowser` for browser flows, which keeps cookies and submits forms with their CSRF fields). Never include the front controller or overwrite PHP superglobals in functional tests.
- Functional tests use dedicated test infrastructure: their own database, broker queues, and worker, so development services never process test data.
- Test asynchronous behavior at two levels: unit-test dispatch decisions and message handlers with PHPUnit mocks, then cover the end-to-end effect in a functional test using the real local broker and service (such as RabbitMQ and MailHog). Poll for the expected result with a bounded timeout, use unique test data, and clean up test-owned data. Keep the broker-backed test separate from fast unit tests.
- Document every JSON API operation in `openapi.yaml` and validate real responses, including problem details, against it in the functional suite.
- Test repositories against the real test database in the functional suite rather than mocking PDO.
- Test security-sensitive HTTP behavior explicitly: state-changing GET requests must have no side effects, and invalid or missing CSRF tokens must prevent the action and return an appropriate error status.
- Test recovery paths for infrastructure failures. In particular, retrying registration for an existing unverified user with valid credentials must allow confirmation email delivery to be retried without creating another account.
- Rate-limit confirmation email resends with a durable database counter, a typed integer maximum, and the rate window expressed in seconds as a typed integer constant. Test the limit and the reset after the window expires.

## Code and architecture

- Keep PHP compatible with the version declared by the project and format it according to PSR-12.
- Prefer PHP 8.5's pipe operator (`|>`) for readable, left-to-right value transformations instead of nesting function calls. Use first-class callables for single-argument steps and a short closure or named method when a step needs additional arguments; do not force a pipe when it obscures the logic. Never wrap a single expression in a closure only to pipe one value into it.
- Omit redundant parentheses around `new` expressions when immediately accessing a member or calling a method.
- Keep controllers thin. Delegate application actions to services; keep business logic, validation, persistence, and session management out of controllers. Controllers own the HTTP response: they map service results and domain exceptions to redirects, rendered views, and status codes.
- Keep request DTOs in a `Request/` subdirectory and response DTOs in a `Response/` subdirectory of their feature (shared ones in `Shared/Request/` and `Shared/Response/`); do not mix them in a generic `Dto/` folder.
- Keep each API action to three steps: parse the request into a request DTO, call one application service, and respond with a response DTO. Request DTOs parse and validate input and throw `InvalidRequestException` (HTTP 400); constrain path parameters in the route (for example `{id:\d+}`); map domain exceptions declaratively with `#[MapException]` instead of `try/catch`; build response bodies in DTOs implementing `Arrayable`, never as arrays in the controller.
- Use lightweight CQRS only where it pays off: when writes enforce invariants under concurrency (stock, money, idempotency) and reads need a different shape than the write model, as in checkout and orders. Model writes as a `Command/` object handled by a `*Handler` that loads the write model, runs in a transaction, and returns only an identifier; model reads as a `Query/` object handled by a `*Handler` that reads a `ReadModel/` view straight from SQL through a read repository, without entities. Both sides share one database; do not add event sourcing or a separate read store. Keep simple CRUD features, such as the catalog, carts, and authentication, on plain application services.
- Accept an `Idempotency-Key` header on POST operations that create money-related resources, store the key with a unique index in the same transaction as the resource, return the existing resource for a repeated key, and reject a key reused for a different request with HTTP 422.
- Lock rows that several requests compete for in a fixed order (ascending primary key), guard counters with conditional `UPDATE ... WHERE` statements, and cover the race with a functional test that sends parallel requests through the web server.
- Application services accept typed arguments, never raw request arrays, and know nothing about URLs, routes, or templates. Report expected failures with specific domain exceptions rather than result objects carrying HTTP details, and catch only the exception types a flow is meant to handle.
- Treat a missing required request field as a bad request (HTTP 400) at the controller boundary; represent missing values with `null`, never with sentinel strings.
- Keep GET requests safe and free of state changes. Complete email confirmation and other mutations with POST requests protected by a session-bound CSRF token.
- Add CSRF tokens to every state-changing browser form and validate them before invoking application services. Reject invalid tokens with HTTP 403.
- Keep the JSON API under `/api/` sessionless: API actions never read the session or cookies and identify resources with unguessable identifiers or explicit credentials, which is why the router skips CSRF validation for `/api/` routes.
- Start browser sessions with strict session ID handling and cookies configured as HttpOnly and SameSite=Lax; set Secure whenever the request uses HTTPS. Regenerate the session ID after successful authentication and after logout.
- Run multi-statement database work through `TransactionRunnerInterface` with an explicit isolation level; unit tests use `ImmediateTransactionRunner`. Keep transactional operations free of side effects outside the database, because deadlocks and lock wait timeouts retry the whole operation.
- Make asynchronous operations recoverable. Provide a documented and tested retry path for messages that could not be dispatched, and ensure the worker restarts after process failure. Keep failed messages inspectable and document how to retry them; a retry must skip, not crash on, messages it cannot decode.
- Treat queued message class names as a persisted contract. Before renaming or moving a message class, drain its queues or keep the old class loadable.
- Read configuration such as DSNs, hosts, ports, credentials, public URLs, and sender addresses only from environment variables. Do not define fallback values for them in PHP code; keep local defaults in `compose.yaml` and `.env.example`, and fail fast when a required variable is missing.
- Keep DRY and KISS in balance: share behavior when there is a clear single responsibility, but avoid generic frameworks, indirection, and abstractions that make a simple flow harder to follow.
- Give each piece of knowledge, such as a session key, route path, token hashing algorithm, or date format, a single owning class; other code uses that owner instead of repeating the value. A feature that needs another feature's state uses its public service, not its internal keys.
- Put domain rules on entities and value objects, for example whether a user is verified or whether a password matches. Avoid boolean flag parameters that switch behavior; use separately named methods.
- Group related feature classes together, use matching namespaces, and use singular names for source directories.
- Organize `src/` by feature first. Keep feature-specific controllers, entities, repositories, services, messages, and infrastructure under that feature; put code in `Shared/` only when multiple features use it. Mirror this structure in `tests/Unit/`.
- Keep feature names such as `Auth/` and `Home/` at the top of `src/`. Do not add top-level type-only folders such as `src/Controller/` or `src/Service/`.
- Place exceptions in an `Exception/` subdirectory and enums in an `Enum/` subdirectory of their feature or shared module, even when there is only one. Create other layer subdirectories when a feature has enough related files to benefit from grouping. Avoid empty directories, and remove them when moving or deleting their last file.
- Declare HTTP routes as method attributes on controllers and register controller instances with the router. Route paths may contain `{name}` segments; route methods receive path parameters through an optional `Request` argument. Reject duplicate routes and route methods that are not public or accept anything other than a single `Request`.
- Keep HTTP request parsing and response rendering at the controller boundary. Application services own use cases, and repositories own persistence. Avoid duplicate view-rendering or response helpers.
- Give each layer a clear responsibility: controllers translate HTTP input into application calls and responses; application services implement use cases; entities hold domain state and rules; repositories load and persist data; infrastructure adapters integrate external systems. Do not move HTTP globals, headers, or view rendering into application services.
- Put cross-cutting HTTP behavior, such as session setup, CSRF validation, request parsing, response writing, and route dispatch, in shared HTTP components injected into controllers, so every controller receives the same policy. Prefer composition over controller base classes. Keep feature-specific business decisions inside that feature.
- Views only produce markup: they never send headers or build response metadata, and they escape output through the shared renderer's escaping helper.
- Keep service factories focused on their runtime: web requests must not initialize worker-only mail handlers or event listeners.
- Represent monetary amounts with `Money\Money` (moneyphp/money) everywhere in the domain; never use floats or bare integers. Store them as an unsigned `BIGINT` in minor units plus a `CHAR(3)` ISO 4217 currency code, and expose them in APIs as `{"amount": "<minor units as string>", "currency": "<code>"}`.
- Hydrate query results into readonly record DTOs marked `#[HydratedRecord]` through `RecordFetcher`, selecting columns with aliases that match the record's properties; records that represent an entity implement `App\Shared\Contract\EntityRecord` and convert themselves with `toEntity()`. Never pass associative row arrays between methods, and return report or listing results as DTOs.
- Generate UUIDs with `symfony/uid` inside domain value objects; never assemble UUID bits by hand. Access-granting ids extend `App\Shared\Identifier\RandomUuid`. Use version 4 for identifiers that act as access tokens, consider version 7 for internal time-ordered keys, and store UUIDs as `BINARY(16)`.
- DTOs that need an array form, such as API response bodies, implement `App\Shared\Contract\Arrayable`.
- Declare explicit types for class constants. Prefer backed enums for public finite sets of values.
- Empty string literals are forbidden. Define string values as constants instead of inline literals, except in enum methods where a literal directly expresses enum behavior.
- Prefer readable path strings using `/` over assembling path segments when practical.

## Dependencies, data, and infrastructure

- Use Composer for PHP dependencies and commit the application lockfile. Declare every PHP extension the application needs at runtime as an `ext-*` requirement, including extensions required by dependencies that the Docker image installs for them (such as `bcmath` for moneyphp/money and `amqp` for Messenger), with test-only extensions in `require-dev`.
- Keep Docker and local service configuration grouped by service. Share repeated Compose configuration through YAML anchors, and give each service only the environment variables its runtime uses.
- Use the configured PHP migration tool for schema changes. Give migrations descriptive names, make them reversible where practical, and never edit a migration after it has been applied. Declare every column `'null' => false` unless `NULL` carries meaning, enforce invariants with database constraints, and date new migrations after the latest committed one.
- Keep application and functional-test databases separate.
- Do not commit local environment files or secrets.
- Ignore `.env` and other local environment files in Git while keeping `.env.example` tracked. Do not rely on `.dockerignore` as Git protection.

## Quality and delivery

- Keep PHPStan at level 8 and PHP CS Fixer configured for PSR-12.
- CI must run both test suites, apply database migrations, and run PHPStan and PHP CS Fixer. CI and local verification run the same `bin/ci` script against the Docker Compose stack; do not duplicate service configuration or environment variables in the workflow file.
- For email confirmation, functional tests must prove that opening the GET link does not verify the account and that an explicit valid POST does. Cover token replay or expiry where repository behavior changes.
- Before pushing, run `bin/ci` locally without a local `.env` override and push only after it passes.
- Write commit messages in scoped Conventional Commits format: `<type>(<scope>): <description>`. Every commit must have a lowercase scope.
