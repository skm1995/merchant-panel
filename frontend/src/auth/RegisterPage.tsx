import { useState, type SubmitEvent } from 'react';
import { Link } from 'react-router';
import { apiClient } from '../api/client';
import { problemMessage } from '../api/problem';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Page } from '../ui/Page';
import { TextField } from '../ui/TextField';

export function RegisterPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [sentTo, setSentTo] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    const { response, error: problem } = await apiClient.POST('/api/v1/registrations', {
      body: { email, password },
    });
    setBusy(false);

    if (response.ok) {
      setError(null);
      setSentTo(email);

      return;
    }

    setError(problemMessage(problem));
  }

  return (
    <Page title="Create an account">
      {sentTo !== null && <Alert tone="success">We sent a confirmation link to {sentTo}.</Alert>}
      {error !== null && <Alert tone="error">{error}</Alert>}
      <form onSubmit={(event) => void submit(event)}>
        <TextField
          label="Email"
          type="email"
          autoComplete="email"
          value={email}
          onChange={setEmail}
        />
        <TextField
          label="Password"
          type="password"
          autoComplete="new-password"
          value={password}
          onChange={setPassword}
        />
        <Button type="submit" busy={busy}>
          Create account
        </Button>
      </form>
      <p>
        <Link to="/login">I already have an account</Link>
      </p>
    </Page>
  );
}
