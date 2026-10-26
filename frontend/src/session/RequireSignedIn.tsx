import { Navigate, Outlet } from 'react-router';
import { useSession } from './useSession';

/**
 * Shows the nested panel routes only to signed-in merchants and sends everyone else to
 * the sign-in page.
 */
export function RequireSignedIn() {
  const { session } = useSession();

  if (session === null) {
    return <p aria-busy="true">Loading…</p>;
  }

  return session.email === null ? <Navigate to="/login" replace /> : <Outlet />;
}
