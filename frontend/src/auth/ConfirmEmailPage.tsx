import { useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { apiClient } from '../api/client';
import { problemMessage } from '../api/problem';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Page } from '../ui/Page';

const TOKEN_PARAMETER = 'token';

/**
 * Opening the emailed link only shows this page; the email is confirmed when the user
 * presses the button, so link scanners that follow links do not confirm accounts.
 */
export function ConfirmEmailPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get(TOKEN_PARAMETER);
  const [confirmed, setConfirmed] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (token === null || token.length === 0) {
    return (
      <Page title="Confirm your email">
        <Alert tone="error">This confirmation link is incomplete.</Alert>
      </Page>
    );
  }

  async function confirm(confirmationToken: string) {
    setBusy(true);
    const { response, error: problem } = await apiClient.POST('/api/v1/email-confirmations', {
      body: { token: confirmationToken },
    });
    setBusy(false);

    if (response.ok) {
      setConfirmed(true);

      return;
    }

    setError(problemMessage(problem));
  }

  if (confirmed) {
    return (
      <Page title="Confirm your email">
        <Alert tone="success">Your email address is confirmed.</Alert>
        <Link to="/login">Sign in</Link>
      </Page>
    );
  }

  return (
    <Page title="Confirm your email">
      {error !== null && <Alert tone="error">{error}</Alert>}
      <Button busy={busy} onClick={() => void confirm(token)}>
        Confirm email
      </Button>
    </Page>
  );
}
