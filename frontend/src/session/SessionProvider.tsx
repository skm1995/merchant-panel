import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { apiClient, rememberCsrfToken } from '../api/client';
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

  return { email: response.email };
}

async function fetchSession(): Promise<Session | null> {
  const { data } = await apiClient.GET(SESSION_PATH);

  return data === undefined ? null : toSession(data);
}

/**
 * Loads the browser session from the API and keeps it, with its CSRF token, for the panel.
 */
export function SessionProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null>(null);

  useEffect(() => {
    let mounted = true;

    void fetchSession().then((loaded) => {
      if (mounted) {
        setSession(loaded);
      }
    });

    return () => {
      mounted = false;
    };
  }, []);

  const signIn = useCallback(async (email: string, password: string): Promise<SignInResult> => {
    const { data, error } = await apiClient.POST(SESSION_PATH, { body: { email, password } });

    if (data === undefined) {
      return { ok: false, message: problemMessage(error) };
    }

    setSession(toSession(data));

    return { ok: true };
  }, []);

  const signOut = useCallback(async (): Promise<void> => {
    await apiClient.DELETE(SESSION_PATH);
    setSession(await fetchSession());
  }, []);

  const value = useMemo<SessionContextValue>(
    () => ({ session, signIn, signOut }),
    [session, signIn, signOut],
  );

  return <SessionContext value={value}>{children}</SessionContext>;
}
