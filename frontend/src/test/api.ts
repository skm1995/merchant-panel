import { http, HttpResponse } from 'msw';
import type { components } from '../api/schema';

type Session = components['schemas']['Session'];
type Problem = components['schemas']['Problem'];

export const CSRF_TOKEN = 'test-csrf-token';
export const SESSION_PATH = '/api/v1/session';

export function anonymousSession(): Session {
  return { authenticated: false, email: null, csrf_token: CSRF_TOKEN };
}

export function signedInSession(email: string): Session {
  return { authenticated: true, email, csrf_token: CSRF_TOKEN };
}

export function problem(status: number, detail: string): HttpResponse<Problem> {
  return HttpResponse.json<Problem>(
    { type: 'about:blank', title: 'Error', status, detail },
    { status, headers: { 'Content-Type': 'application/problem+json' } },
  );
}

export function sessionHandler(session: Session) {
  return http.get(SESSION_PATH, () => HttpResponse.json<Session>(session));
}
