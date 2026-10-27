# 0005. Session cookies, not JWT, for the merchant panel

Accepted, 2026-10-26.

## Context

The React panel needs to know who is signed in. The common alternatives are a server-side
session with a cookie and a JSON Web Token kept by the browser. The panel and the API are
served from one origin.

## Decision

Use a server-side session identified by an HttpOnly, `SameSite=Lax`, `Secure` cookie, with a
CSRF token sent in a header for every state change. Sessions live in Redis. Integrations that
are not browsers use an API key instead.

## Alternatives

- **JWT in local storage.** Readable by any script, so an XSS flaw leaks the token; it cannot
  be revoked before it expires without a server-side list, which brings back the session.
- **JWT in a cookie.** Avoids script access, but still needs CSRF protection and revocation,
  and makes the cookie large.

## Consequences

- Signing out and revoking merchant access take effect at once, because the server decides on
  every request.
- Scripts never see the credential; the CSRF token is useless on its own.
- This matches how established e-commerce platforms, such as IdoSell, authenticate their
  panels, with API keys for integrations.
- A second, unrelated client, such as a mobile app, would need a token-based scheme.
