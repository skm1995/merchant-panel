# 0014. One server, deployed from CI with Docker Compose

Accepted, 2026-10-26.

## Context

The project needs a live demo at no cost. It runs about ten containers, which free
platform-as-a-service tiers do not host: they run single services and put idle ones to sleep.

## Decision

Run the whole stack on one Oracle Cloud Always Free ARM server with
[`compose.prod.yaml`](../../compose.prod.yaml). After CI passes on `master`, a GitHub Actions
job runs [`bin/deploy`](../../bin/deploy): it prepares the server idempotently, uploads the
commit, writes `.env` from GitHub secrets, builds the images on the server, and checks that
the API answers over HTTPS. Domains come from is-a.dev; the personal site is on GitHub Pages.

## Alternatives

- **Render, Railway, or Fly.io free tiers.** Sleep after inactivity, no free MySQL and
  RabbitMQ, or no free tier for new accounts.
- **Images built in CI and pushed to a registry.** Faster deploys and rollbacks, kept for the
  roadmap.

## Consequences

- Every green commit is live within minutes, with no manual step.
- One server is a single point of failure, with backups on the same machine.
- The first build on the server takes several minutes; later ones reuse Docker's layer cache.
