import { createContext } from 'react';

export interface Session {
  email: string | null;
  /** Whether the account may use the merchant panel. */
  merchant: boolean;
}

export type SignInResult = { ok: true } | { ok: false; message: string };

export interface SessionContextValue {
  /** The current session, or null while it is loading. */
  session: Session | null;
  signIn: (email: string, password: string) => Promise<SignInResult>;
  signOut: () => Promise<void>;
}

export const SessionContext = createContext<SessionContextValue | null>(null);
