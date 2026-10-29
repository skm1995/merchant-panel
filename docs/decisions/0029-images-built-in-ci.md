# 0029. Production images built in CI and pulled by the server

Accepted, 2026-10-29. Supersedes the image building of [0014](0014-one-server-deployed-from-ci.md).

## Context

The server built the PHP and Caddy images during every deployment
([0014](0014-one-server-deployed-from-ci.md)). A deploy took minutes of the server's CPU while
the demo ran, a build failure surfaced only on the server, and rolling back meant reverting a
commit and waiting for a new build. The server is an ARM machine, and GitHub offers ARM
runners for public repositories at no cost.

## Decision

The `images` job runs [`bin/publish-images`](../../bin/publish-images) on an ARM runner, at
the same time as `quality`: it builds the production images from the same Dockerfiles and
pushes them to the GitHub Container Registry, tagged with the commit. `compose.prod.yaml`
names the images with that tag and builds nothing. [`bin/deploy`](../../bin/deploy) logs the
server in with the deploy job's short-lived token, pulls every image before it replaces the
running release, logs out, and starts the services without building.

## Alternatives

- **Keep building on the server.** Nothing to set up, but slow, and rollback stays a revert.
- **Build in CI with QEMU emulation on an x86 runner.** Works for private repositories too,
  but several times slower.
- **A long-lived registry token on the server.** Simpler pulls, but a secret that outlives
  the deployment.
- **Docker Hub.** Another account and secret; the GitHub registry already trusts the
  workflow's token.

## Consequences

- A broken build or a failed pull stops the deployment before the demo is touched.
- Rolling back is re-running the CI run of an earlier commit: its images are still in the
  registry, so the deploy only pulls them.
- The server no longer compiles PHP extensions or builds the React app during a deployment.
- The images job may write packages; every other job keeps a read-only token.
- The registry keeps every commit's images; the server keeps only those in use.
