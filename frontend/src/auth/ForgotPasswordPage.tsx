import { useState, type SubmitEvent } from 'react';
import { Link } from 'react-router';
import { apiClient } from '../api/client';
import { problemMessage } from '../api/problem';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Page } from '../ui/Page';
import { TextField } from '../ui/TextField';

/**
 * Asks for a password reset link. The answer is the same for every address, so the page
 * never reveals which ones have an account.
 */
export function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [sentTo, setSentTo] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    const { response, error: problem } = await apiClient.POST('/api/v1/password-resets', {
      body: { email },
    });
    setBusy(false);

    if (response.ok) {
      setError(null);
      setSentTo(email);

      return;
    }

    setSentTo(null);
    setError(problemMessage(problem));
  }

  return (
    <Page title="Reset your password">
      {sentTo !== null && (
        <Alert tone="success">
          If {sentTo} belongs to a confirmed account, a reset link is on its way. It works for an
          hour.
        </Alert>
      )}
      {error !== null && <Alert tone="error">{error}</Alert>}
      <form onSubmit={(event) => void submit(event)}>
        <TextField
          label="Email"
          type="email"
          autoComplete="email"
          value={email}
          onChange={setEmail}
        />
        <Button type="submit" busy={busy}>
          Send reset link
        </Button>
      </form>
      <p>
        <Link to="/login">Back to sign in</Link>
      </p>
    </Page>
  );
}
