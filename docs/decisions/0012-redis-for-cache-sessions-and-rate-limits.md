# 0012. Redis for the cache, sessions, and rate limits

Accepted, 2026-10-25 for the cache; sessions and rate limits since 2026-10-27.

## Context

Product reads are frequent, sessions stored in files inside the PHP container were lost on
every deployment, and rate limits need counters shared by all PHP processes.

## Decision

Use Redis for all three, in separate databases: the product cache with stampede protection
(a short lock and probabilistic early refresh), sessions through the phpredis session handler
configured in the PHP-FPM pool, and fixed-window rate-limit counters. Redis is optional for
requests: when it is unreachable, the cache computes values from MySQL and the rate limiter
lets requests through, both logging a warning.

## Alternatives

- **APCu for the cache.** Fast, but per container and lost on deploy.
- **Database-backed sessions and counters.** No new service, but more load on MySQL.

## Consequences

- Deployments no longer sign merchants out, and several PHP containers could share state.
- A Redis outage degrades performance and switches rate limiting off, but does not stop the
  shop; signed-in sessions are unavailable until Redis returns.
