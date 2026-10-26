import createClient, { type Middleware } from 'openapi-fetch';
import type { paths } from './schema';

const CSRF_TOKEN_HEADER = 'X-CSRF-Token';
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

let csrfToken: string | null = null;

/**
 * Keeps the session's CSRF token, which GET /api/v1/session issues, for later state changes.
 */
export function rememberCsrfToken(token: string): void {
  csrfToken = token;
}

const csrfMiddleware: Middleware = {
  onRequest({ request }) {
    if (!SAFE_METHODS.has(request.method) && csrfToken !== null) {
      request.headers.set(CSRF_TOKEN_HEADER, csrfToken);
    }

    return request;
  },
};

/**
 * Typed client for the backend API. Paths, parameters, and response bodies come from the
 * types generated from openapi/, so a contract change breaks the build instead of the screen.
 * Requests stay on the page's origin and carry the session cookie. The global fetch is looked
 * up on every call, so test doubles such as MSW can intercept it.
 */
export const apiClient = createClient<paths>({
  baseUrl: window.location.origin,
  credentials: 'same-origin',
  fetch: (request) => globalThis.fetch(request),
});

apiClient.use(csrfMiddleware);
