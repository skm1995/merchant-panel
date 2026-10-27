import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { useState } from 'react';
import {
  anonymousSession,
  problem,
  SESSION_PATH,
  sessionHandler,
  merchantSession,
} from '../test/api';
import { ORDERS_PATH } from '../test/panel';
import { renderApp } from '../test/render';
import { server } from '../test/server';
import { SessionProvider } from './SessionProvider';
import { useSession } from './useSession';

const EMAIL = 'merchant@example.com';
const PASSWORD = 'merchant-password';
const INVALID_CREDENTIALS = 'Invalid email or password.';

function SessionProbe() {
  const { session, signIn, signOut } = useSession();
  const [error, setError] = useState<string | null>(null);

  if (session === null) {
    return <p>Loading session</p>;
  }

  return (
    <div>
      <p>{session.email ?? 'Anonymous'}</p>
      {error !== null && <p role="alert">{error}</p>}
      <button
        type="button"
        onClick={() => {
          void signIn(EMAIL, PASSWORD).then((result) => {
            setError(result.ok ? null : result.message);
          });
        }}
      >
        Sign in
      </button>
      <button type="button" onClick={() => void signOut()}>
        Sign out
      </button>
    </div>
  );
}

function renderProbe() {
  render(
    <SessionProvider>
      <SessionProbe />
    </SessionProvider>,
  );
}

describe('SessionProvider', () => {
  it('loads the current session', async () => {
    server.use(sessionHandler(merchantSession(EMAIL)));

    renderProbe();

    expect(screen.getByText('Loading session')).toBeInTheDocument();
    expect(await screen.findByText(EMAIL)).toBeInTheDocument();
  });

  it('signs in and out', async () => {
    let session = anonymousSession();
    server.use(
      http.get(SESSION_PATH, () => HttpResponse.json(session)),
      http.post(SESSION_PATH, () => {
        session = merchantSession(EMAIL);

        return HttpResponse.json(session);
      }),
      http.delete(SESSION_PATH, () => {
        session = anonymousSession();

        return new HttpResponse(null, { status: 204 });
      }),
    );
    renderProbe();
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Sign in' }));
    expect(await screen.findByText(EMAIL)).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Sign out' }));
    expect(await screen.findByText('Anonymous')).toBeInTheDocument();
  });

  it('reports why signing in failed', async () => {
    server.use(
      sessionHandler(anonymousSession()),
      http.post(SESSION_PATH, () => problem(401, INVALID_CREDENTIALS)),
    );
    renderProbe();
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Sign in' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(INVALID_CREDENTIALS);
    expect(screen.getByText('Anonymous')).toBeInTheDocument();
  });

  it('sends the merchant to sign in when the session has expired', async () => {
    server.use(
      http.get(SESSION_PATH, () => HttpResponse.json(merchantSession(EMAIL)), { once: true }),
      sessionHandler(anonymousSession()),
      http.get(ORDERS_PATH, () => problem(401, 'Sign in to continue.')),
    );
    renderApp('/orders');

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });
});
