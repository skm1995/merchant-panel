import { useState, type SubmitEvent } from 'react';
import { Link, useNavigate } from 'react-router';
import { useSession } from '../session/useSession';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Page } from '../ui/Page';
import { TextField } from '../ui/TextField';

export function LoginPage() {
  const { signIn } = useSession();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    const result = await signIn(email, password);
    setBusy(false);

    if (result.ok) {
      void navigate('/');

      return;
    }

    setError(result.message);
  }

  return (
    <Page title="Sign in">
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
          autoComplete="current-password"
          value={password}
          onChange={setPassword}
        />
        <Button type="submit" busy={busy}>
          Sign in
        </Button>
      </form>
      <p>
        <Link to="/register">Create an account</Link>
      </p>
    </Page>
  );
}
