# 0003. Discover services, routes, and messages from attributes

Accepted, 2026-10-25.

## Context

Hand-written registries of services, routes, and message handlers grow with every feature,
drift from the code, and cause merge conflicts.

## Decision

Derive them from the code. The container autowires constructors and binds interfaces with one
implementation; `#[DefaultImplementation]`, `#[ServiceFactory]`, `#[Env]`, and `#[Autowire]`
cover the exceptions. Controllers are found in `src/*/Controller` by their `#[Route]` methods,
and messages and handlers by `#[Message]` and `#[AsMessageHandler]`. The discovery result is
cached in a PHP file that OPcache serves and is rebuilt when a source file changes.

## Alternatives

- **Configuration files.** Explicit, but a second place to keep in sync.
- **A full framework container.** Compiles and optimizes better, but brings the framework.

## Consequences

- Adding a route, a service, or a handler is adding a class or an attribute, nothing more.
- Discovery is reflection at build time of the cache; a stale cache was once a real bug,
  fixed by invalidating OPcache for scanned files when the cache is rebuilt.
- `bin/container-debug.php` shows the resulting bindings.
