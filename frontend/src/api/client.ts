import createClient from 'openapi-fetch';
import type { paths } from './schema';

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
