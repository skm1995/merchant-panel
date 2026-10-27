# 0013. Separate backend and frontend in one repository

Accepted, 2026-10-26.

## Context

The panel was first rendered by PHP. A modern merchant panel, and the job offer the project
was shaped for, call for a React frontend that uses the backend only through its API.

## Decision

Move the PHP application into [`backend/`](../../backend) and add a React app in
[`frontend/`](../../frontend), in the same repository. The root keeps only what spans both:
the OpenAPI contract, Docker configuration, the CI and deploy scripts, and the documentation.
One `bin/ci` verifies both sides, and every commit can change the contract and both sides
together.

## Alternatives

- **Two repositories.** Clear ownership, but contract changes need coordinated releases.
- **Server-rendered PHP pages.** Simpler, but not what the panel or the offer called for.

## Consequences

- The backend is a pure JSON API, and the panel is one of its clients.
- Each side has its own `AGENTS.md`, tooling, and tests, while CI and deployment stay one
  pipeline.
