import { Navigate, Outlet } from 'react-router';
import { Button } from '../ui/Button';
import { Page } from '../ui/Page';
import { useSession } from './useSession';

/**
 * Shows the nested panel routes only to merchants. Anonymous visitors go to the sign-in
 * page; a signed-in account without merchant access learns how to get it.
 */
export function RequireMerchant() {
  const { session, signOut } = useSession();

  if (session === null) {
    return <p aria-busy="true">Loading…</p>;
  }

  if (session.email === null) {
    return <Navigate to="/login" replace />;
  }

  if (!session.merchant) {
    return (
      <Page title="No panel access">
        <p>
          You are signed in as {session.email}, but this account has no access to the merchant panel
          yet. Ask the shop&apos;s operator to grant it, then reload this page.
        </p>
        <Button variant="secondary" onClick={() => void signOut()}>
          Sign out
        </Button>
      </Page>
    );
  }

  return <Outlet />;
}
