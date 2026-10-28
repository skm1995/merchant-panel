# 0025. Black Friday simulation through the public API, from its own process

Accepted, 2026-10-28.

## Context

The shop's main claim is that checkout neither oversells nor loses orders when many buyers
arrive at once. The k6 load test proves it in CI, but nobody looking at the demo can see it.
The panel's demo account is public, so whatever starts load must bound it.

## Decision

A merchant starts a simulation from the panel. A synchronous command takes a single slot in
Redis for the run and a cool-down, resets five products' stock, and records an asynchronous
`RunSimulationCommand` in the outbox. A dedicated `simulator` process, the only consumer of
the `simulation` queue, runs fifty buyers for sixty seconds through the public address, so
they pass Caddy, PHP-FPM, every middleware, and the payment webhook like customers. Their
requests carry a per-deployment key that skips the rate limits only. At the end, units taken
from stock are compared with units in paid orders, and the panel shows the result.

## Alternatives

- **Calling the handlers in-process.** No HTTP and no key, but it would skip the proxy, the
  middleware, and the webhook signature, which are part of what a Black Friday tests.
- **Running k6 on demand.** The proven tool, but the panel would need to start a container,
  which means giving the web server access to Docker.
- **Running the load in the worker.** One process fewer, but a minute of load would delay
  confirmation emails and order expiries.
- **Exempting the server's own addresses from the rate limits.** No key, but tests run from
  the same network and rely on the limits.

## Consequences

- The demo shows its main property live, and the same minute appears on the Kibana dashboard.
- One simulation runs at a time and the next waits for the cool-down, so the public account
  cannot keep the server busy.
- The buyers share the server with the shop, which limits how much load they can create.
