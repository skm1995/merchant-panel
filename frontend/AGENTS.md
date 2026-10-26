# Frontend guide

A React and TypeScript application that talks to the backend only through the JSON API. The root `AGENTS.md` applies as well.

## Testing

- Follow TDD with Vitest and React Testing Library. Test what a user sees and does: query by role, label, and text, interact through `@testing-library/user-event`, and never assert on component state, implementation details, or snapshots.
- Keep each test next to its module as `<Module>.test.ts(x)`.
- Fake the API at the network boundary (`fetch`), never by mocking the API client module, and build fake responses from the generated types so they cannot drift from the contract.

## API

- Call the backend only through `src/api/client.ts`. Paths, parameters, and bodies are typed from `src/api/schema.ts`, which `npm run api:types` generates from `../openapi/`; never edit it by hand, and `npm run api:check` fails when it is stale.
- The API shares the page's origin, so the session cookie travels automatically; never store credentials or tokens in `localStorage`.

## Code style

- Strict TypeScript; do not use `any`, non-null assertions, or type casts to silence the compiler.
- Function components and hooks only; name components in PascalCase and hooks `use*`.
- ESLint and Prettier decide formatting and common mistakes; `bin/ci` runs both.
