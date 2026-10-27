import createClient, { type Middleware } from 'openapi-fetch';
import type { paths } from './schema';

const CSRF_TOKEN_HEADER = 'X-CSRF-Token';
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);
const SESSION_PATH = '/api/v1/session';
const UNAUTHORIZED = 401;

let csrfToken: string | null = null;
let sessionExpiredListener: (() => void) | null = null;

/**
 * Keeps the session's CSRF token, which GET /api/v1/session issues, for later state changes.
 */
export function rememberCsrfToken(token: string): void {
  csrfToken = token;
}

/**
 * Registers what to do when the server no longer knows the session, for example after it
 * expired, and returns a function that removes the listener. A failed sign-in also answers
 * 401, so responses from the session resource itself never count.
 */
export function onSessionExpired(listener: () => void): () => void {
  sessionExpiredListener = listener;

  return () => {
    if (sessionExpiredListener === listener) {
      sessionExpiredListener = null;
    }
  };
}

const sessionExpiryMiddleware: Middleware = {
  onResponse({ request, response }) {
    if (response.status === UNAUTHORIZED && new URL(request.url).pathname !== SESSION_PATH) {
      sessionExpiredListener?.();
    }

    return response;
  },
};

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

apiClient.use(csrfMiddleware, sessionExpiryMiddleware);
