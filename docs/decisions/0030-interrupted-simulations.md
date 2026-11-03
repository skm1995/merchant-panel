# 0030. A simulation that never reports its check is interrupted

Accepted, 2026-11-03.

## Context

A Black Friday run is "running" until its check is saved ([0025](0025-black-friday-through-the-public-api.md)).
If the simulator stops before saving it, nothing ever saves it: the panel keeps asking every
second, the start button stays disabled, and the merchant cannot start another run from the
page. The slot in Redis expires after the run and its cool-down, but the page never looked at it.

## Decision

The state of a run is derived from the time when the panel asks. It is finished once its check
is saved. Until two minutes after its end it is running, which leaves time for the last orders
in flight. After that, a run without a check is interrupted. The panel stops polling, enables
the start button, and says that the counters may be incomplete. Starting again is still
refused while the slot is held, with the seconds to wait.

## Alternatives

- **A watchdog that writes the failure.** A process would have to notice the stopped
  simulator and save the run, which needs another process to be alive when the first one died.
- **A `failed` state written by the handler.** It covers a check that throws, but not a
  simulator that is killed mid-run, which is the case that left the panel stuck.
- **Timing out in the panel only.** The server would still say "running", so a second tab or a
  reload would show the same stuck page.

## Consequences

- A stopped run no longer blocks the page; the merchant sees its partial counters and can
  start again once the cool-down has passed.
- An interrupted run has no check, so it makes no claim that the stock matched the paid orders.
- The state depends on the server's clock, as the cool-down already does.
