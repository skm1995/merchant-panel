# Project instructions

- Keep Docker configuration under `docker/`, grouped by service.
- Use Composer for PHP dependencies and commit `composer.lock` for applications.
- Every class, function, method, and behavior in application code must have PHPUnit unit tests covering its success and failure paths where applicable.
- Run the PHPUnit test suite before considering a change complete.
- Empty string literals are forbidden in code.
- Define string values used by code as constants instead of inline string literals.
- Declare an explicit type on every class constant, such as `string`, `int`, or `bool`.
- Prefer backed enums for public sets of values instead of public class constants.
- Keep a controller's route path in that controller as a typed `public const string`; do not create a route enum for a single controller route.
- Use `/` directly in path strings; avoid concatenating path segments with `DIRECTORY_SEPARATOR` when a readable path format string is sufficient.
- All PHP code must follow PSR-12.
- Keep PHPStan at level 8 and PHP CS Fixer configured for PSR-12.
- Write commit messages using the Conventional Commits format.
- Keep the PHP runtime compatible with the PHP version declared in `composer.json`.
- Name directories under `src/` in the singular, such as `Controller`, `Model`, `Enum`, and `Core`. Directories outside `src/`, such as `views/`, may use plural names.
- Configure local development services through the root `compose.yaml` file.
- Do not commit local environment files or secrets.
