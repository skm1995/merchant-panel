# Frontend guide

A React and TypeScript application that talks to the backend only through the JSON API. The root `AGENTS.md` applies as well.

## Testing

- Follow TDD with Vitest and React Testing Library. Test what a user sees and does: query by role, label, and text, interact through `@testing-library/user-event`, and never assert on component state, implementation details, or snapshots.
- Keep each test next to its module as `<Module>.test.ts(x)`.
- Fake the API with MSW (`src/test/server.ts`), never by mocking the API client module. Build fake responses with the typed helpers in `src/test/api.ts` so they cannot drift from the contract; a request without a handler fails the test.

## API

- Call the backend only through `src/api/client.ts`. Paths, parameters, and bodies are typed from `src/api/schema.ts`, which `npm run api:types` generates from `../openapi/`; never edit it by hand, and `npm run api:check` fails when it is stale.
- The API shares the page's origin, so the session cookie travels automatically; never store credentials or tokens in `localStorage`.

## Design system

- Build screens from the components in `src/ui/` (`Button`, `TextField`, `Alert`, `Page`, …); add a component there, with its own test, instead of styling one-off elements in a screen.
- Components are accessible by default: every input has a label, errors are linked with `aria-describedby`, and alerts use `role="alert"` or `role="status"`.
- Style with CSS modules next to the component and the design tokens (CSS variables) in `src/ui/tokens.css`; never hard-code colors or spacing.

## Sessions

- `SessionProvider` loads the session and keeps its CSRF token; screens read it with `useSession()`. The API client adds the token to every state-changing request.

## Code style

- Strict TypeScript; do not use `any`, non-null assertions, or type casts to silence the compiler.
- Function components and hooks only; name components in PascalCase and hooks `use*`.
- ESLint and Prettier decide formatting and common mistakes; `bin/ci` runs both.
