import { useSession } from '../session/useSession';
import { Button } from '../ui/Button';
import { Page } from '../ui/Page';

export function HomePage() {
  const { session, signOut } = useSession();

  return (
    <Page title="Merchant Panel">
      <p>Signed in as {session?.email}</p>
      <Button variant="secondary" onClick={() => void signOut()}>
        Sign out
      </Button>
    </Page>
  );
}
