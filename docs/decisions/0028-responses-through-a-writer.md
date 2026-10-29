# 0028. Responses written through a writer, not PHP's globals

Accepted, 2026-10-29.

## Context

The `Responder` and two middleware wrote responses with `http_response_code()`, `header()`,
and `echo`, and the access log read the status back with `http_response_code()`. That state
belongs to the PHP process, not to a request, so unit tests of the HTTP layer captured output
with `ob_start()` and reset the status in `tearDown()` to keep tests from affecting each
other; nine test classes did so, and headers such as `ETag` or `Location` could not be
checked at all.

## Decision

Everything that answers a request writes through
[`ResponseWriterInterface`](../../backend/src/Shared/Http/ResponseWriterInterface.php):
status, headers, body, and the status written so far for the access log. Its production
implementation, [`PhpResponseWriter`](../../backend/src/Shared/Http/PhpResponseWriter.php), is
the only class that calls PHP's response functions. Unit tests give the `Responder` a
`RecordingResponseWriter` and assert on what it recorded.

## Alternatives

- **PSR-7 responses returned by controllers and PSR-15 middleware.** The textbook design and
  the most testable, but it changes the signature of every controller and middleware for the
  same benefit in tests.
- **Keep the globals and reset them in tests.** No change, but every test of the HTTP layer
  depends on hidden process state.

## Consequences

- Unit tests read the status, headers, and body without output buffering or `tearDown()`
  resets, and now check the `ETag`, `Location`, `Content-Type`, and `X-Request-Id` headers.
- Controllers and their signatures stay as they were.
- The PHP session still lives in `$_SESSION`, so tests of session code still save and
  restore it.
