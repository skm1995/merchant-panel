import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { apiClient, onSessionExpired, rememberCsrfToken } from '../api/client';
import { problemMessage } from '../api/problem';
import type { components } from '../api/schema';
import {
  SessionContext,
  type Session,
  type SessionContextValue,
  type SignInResult,
} from './SessionContext';

const SESSION_PATH = '/api/v1/session';

function toSession(response: components['schemas']['Session']): Session {
  rememberCsrfToken(response.csrf_token);

  return { email: response.email, merchant: response.merchant };
}

/** The session, or null when the API answered with an error or could not be reached. */
async function fetchSession(): Promise<Session | null> {
  const { data } = await apiClient.GET(SESSION_PATH);

  return data === undefined ? null : toSession(data);
}

/**
 * Loads the browser session from the API and keeps it, with its CSRF token, for the panel.
 */
export function SessionProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null>(null);
  const [failed, setFailed] = useState(false);

  const showSession = useCallback((loaded: Session | null) => {
    setSession(loaded);
    setFailed(loaded === null);
  }, []);

  useEffect(() => {
    let mounted = true;

    void fetchSession().then((loaded) => {
      if (mounted) {
        showSession(loaded);
      }
    });

    return () => {
      mounted = false;
    };
  }, [showSession]);

  // The server forgot the session, so load the anonymous one it now has, with its CSRF
  // token; the panel routes then send the merchant to sign in again.
  useEffect(
    () =>
      onSessionExpired(() => {
        void fetchSession().then(showSession);
      }),
    [showSession],
  );

  const signIn = useCallback(
    async (email: string, password: string): Promise<SignInResult> => {
      const { data, error } = await apiClient.POST(SESSION_PATH, { body: { email, password } });

      if (data === undefined) {
        return { ok: false, message: problemMessage(error) };
      }

      showSession(toSession(data));

      return { ok: true };
    },
    [showSession],
  );

  const signOut = useCallback(async (): Promise<void> => {
    await apiClient.DELETE(SESSION_PATH);
    showSession(await fetchSession());
  }, [showSession]);

  const value = useMemo<SessionContextValue>(
    () => ({ session, failed, signIn, signOut }),
    [session, failed, signIn, signOut],
  );

  return <SessionContext value={value}>{children}</SessionContext>;
}
