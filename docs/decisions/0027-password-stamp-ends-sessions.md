# 0027. A password stamp in the session ends older sessions

Accepted, 2026-10-29.

## Context

Password reset ([flow](../flows/password-reset.md)) must sign out everyone who still has a
session with the old password: a reset often follows a stolen password. Sessions live in
Redis under random ids ([0012](0012-redis-for-cache-sessions-and-rate-limits.md)), and nothing
lists the sessions of one account. The roadmap proposed such a list, a Redis set of session
ids per account, kept up to date at every sign-in, sign-out, and expiry.

## Decision

At sign-in, [`CurrentUser`](../../backend/src/Auth/Infrastructure/Session/CurrentUser.php)
stores the account's password stamp in the session next to its email: a SHA-256 fingerprint of
the password hash. On every request it reads the account, which it already did to check
merchant access, and treats the session as signed out when the account is gone or the stamps
differ. A new password has a new hash, so every older session stops matching.

## Alternatives

- **A Redis set of session ids per account.** Deletes the sessions at once, but every
  sign-in, sign-out, and session expiry has to keep the set right, and a stale entry is
  invisible.
- **A `password_changed_at` column compared with the sign-in time.** The same idea with a
  migration, and two events in one second would compare equal.
- **Short-lived sessions only.** Simple, but a stolen session keeps working until it
  expires.

## Consequences

- A reset signs out every browser at its next request, with no new table or Redis structure.
- Every request with a session reads the account once; it is a lookup by the unique email.
- Sessions from before this change carry no stamp, so the deployment signed everyone out once.
- A future "change password" or "sign out everywhere" works the same way: change what the
  stamp is derived from.
