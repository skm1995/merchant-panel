import { useState, type SubmitEvent } from 'react';
import { Link, useSearchParams } from 'react-router';
import { apiClient } from '../api/client';
import { problemMessage } from '../api/problem';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Page } from '../ui/Page';
import { TextField } from '../ui/TextField';

const TOKEN_PARAMETER = 'token';

/**
 * Sets a new password with the token from the emailed link; the server ends every session of
 * the account, so the merchant signs in again with the new password.
 */
export function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get(TOKEN_PARAMETER);
  const [password, setPassword] = useState('');
  const [changed, setChanged] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (token === null || token.length === 0) {
    return (
      <Page title="Set a new password">
        <Alert tone="error">This reset link is incomplete.</Alert>
        <Link to="/forgot-password">Ask for a new link</Link>
      </Page>
    );
  }

  async function submit(event: SubmitEvent<HTMLFormElement>, resetToken: string) {
    event.preventDefault();
    setBusy(true);
    const { response, error: problem } = await apiClient.POST(
      '/api/v1/password-reset-confirmations',
      { body: { token: resetToken, password } },
    );
    setBusy(false);

    if (response.ok) {
      setChanged(true);

      return;
    }

    setError(problemMessage(problem));
  }

  if (changed) {
    return (
      <Page title="Set a new password">
        <Alert tone="success">Your password is changed. Sign in with the new one.</Alert>
        <Link to="/login">Sign in</Link>
      </Page>
    );
  }

  return (
    <Page title="Set a new password">
      {error !== null && (
        <>
          <Alert tone="error">{error}</Alert>
          <Link to="/forgot-password">Ask for a new link</Link>
        </>
      )}
      <form onSubmit={(event) => void submit(event, token)}>
        <TextField
          label="New password"
          type="password"
          autoComplete="new-password"
          value={password}
          onChange={setPassword}
        />
        <Button type="submit" busy={busy}>
          Set new password
        </Button>
      </form>
    </Page>
  );
}
