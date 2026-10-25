# Project guide

Use these rules when changing this project. Prefer its existing patterns and dependencies; keep new code consistent with the surrounding design.

## Development workflow

- Follow TDD: first write a failing test for the expected behavior, then make the smallest change that passes it, and refactor while keeping tests green.
- Cover application behavior with PHPUnit, including relevant failure paths.
- Keep unit and functional tests in separate suites. Run both suites before considering a change complete.
- Functional tests must use a dedicated test database, apply migrations first, and clean up their data. Never run them against the development database.
- Test HTTP routing through the configured web server in the functional suite; do not rely only on directly including the front controller.
- Test asynchronous behavior at two levels: unit-test dispatch decisions and message handlers with PHPUnit mocks, then cover the end-to-end effect in a functional test using the real local broker and service (such as RabbitMQ and MailHog). Poll for the expected result with a bounded timeout, use unique test data, and clean up test-owned data. Keep the broker-backed test separate from fast unit tests.

## Code and architecture

- Keep PHP compatible with the version declared by the project and format it according to PSR-12.
- Prefer PHP 8.5's pipe operator (`|>`) for readable, left-to-right value transformations instead of nesting function calls. Use first-class callables for single-argument steps and a short closure or named method when a step needs additional arguments; do not force a pipe when it obscures the logic.
- Omit redundant parentheses around `new` expressions when immediately accessing a member or calling a method.
- Keep controllers thin. Delegate application actions and response handling to services; keep business logic, validation, persistence, and session management out of controllers.
- Group related feature classes together, use matching namespaces, and use singular names for source directories.
- Declare explicit types for class constants. Prefer backed enums for public finite sets of values.
- Empty string literals are forbidden. Define string values as constants instead of inline literals, except in enum methods where a literal directly expresses enum behavior.
- Prefer readable path strings using `/` over assembling path segments when practical.

## Dependencies, data, and infrastructure

- Use Composer for PHP dependencies and commit the application lockfile.
- Keep Docker and local service configuration grouped by service.
- Use the configured PHP migration tool for schema changes. Give migrations descriptive names, make them reversible where practical, and never edit a migration after it has been applied.
- Keep application and functional-test databases separate.
- Do not commit local environment files or secrets.

## Quality and delivery

- Keep PHPStan at level 8 and PHP CS Fixer configured for PSR-12.
- CI must run both test suites, apply database migrations, and run PHPStan and PHP CS Fixer.
- Before pushing, run the full CI verification locally: migrations, unit tests, functional tests with required services, PHPStan, and PHP CS Fixer. Push only after every check passes.
- Write commit messages in scoped Conventional Commits format: `<type>(<scope>): <description>`. Every commit must have a lowercase scope.
