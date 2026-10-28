# 0019. Merged coverage of all suites, with a minimum that only rises

Accepted, 2026-10-28.

## Context

With [outside-in tests](0018-outside-in-tests.md), unit tests alone cover about 72% of the
backend on purpose, so a threshold on them would ask for exactly the mock-heavy tests the
project moved away from. The functional tests send HTTP requests to a separate PHP-FPM
process, whose code a coverage tool in the test process does not see.

## Decision

`bin/ci` measures the coverage of every suite and merges it. The unit and functional suites
write their coverage with PCOV; the test web server loads
[`docker/php/coverage/collect.php`](../../docker/php/coverage/collect.php) through
`PHP_INI_SCAN_DIR` and records every request of the functional and end-to-end tests, after
saving the session and sending the response. `phpcov merge` writes an HTML report and a Clover
file to `build/coverage/`, and
[`check-coverage.php`](../../backend/tools/check-coverage.php) fails the run below its
minimum. The frontend's Vitest suite has its own minimums in
[`vite.config.ts`](../../frontend/vite.config.ts). Each minimum trails the measured coverage
by about two points and is raised by hand when coverage grows.

PCOV measures the coverage because it does nothing else and costs a few seconds per run;
Xdebug stays in the development image for debugging. Neither is in the production image.

## Alternatives

- **A threshold on unit coverage.** Rewards tests written for the number.
- **Coverage of changed lines only (patch coverage).** Common in pull-request workflows, but
  this project pushes to `master`, where the base to compare against is unclear.
- **Xdebug for coverage.** Works, but slows every request of the test server severalfold.

## Consequences

- The first measurement: 97.4% of backend lines and 95.9% of frontend lines.
- A feature without tests, or dead code, lowers the number and fails the run; small changes
  do not.
- The long-running worker is not measured; its handlers have unit tests and the paths through
  the broker have functional tests.
