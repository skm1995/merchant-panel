import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import {
  anonymousSession,
  problem,
  SESSION_PATH,
  sessionHandler,
  merchantSession,
} from '../test/api';
import { ordersHandler } from '../test/panel';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const EMAIL = 'merchant@example.com';
const PASSWORD = 'merchant-password';

describe('LoginPage', () => {
  it('signs in and opens the panel', async () => {
    let submitted: unknown = null;
    server.use(
      sessionHandler(anonymousSession()),
      http.post(SESSION_PATH, async ({ request }) => {
        submitted = await request.json();

        return HttpResponse.json(merchantSession(EMAIL));
      }),
      ordersHandler([]),
    );
    renderApp('/login');
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('Email'), EMAIL);
    await user.type(screen.getByLabelText('Password'), PASSWORD);
    await user.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(await screen.findByText(`Signed in as ${EMAIL}`)).toBeInTheDocument();
    expect(submitted).toEqual({ email: EMAIL, password: PASSWORD });
  });

  it('explains why signing in failed', async () => {
    server.use(
      sessionHandler(anonymousSession()),
      http.post(SESSION_PATH, () => problem(401, 'Invalid email or password.')),
    );
    renderApp('/login');
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('Email'), EMAIL);
    await user.type(screen.getByLabelText('Password'), PASSWORD);
    await user.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Invalid email or password.');
    expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });

  it('leads to registration', async () => {
    server.use(sessionHandler(anonymousSession()));
    renderApp('/login');

    await userEvent.setup().click(await screen.findByRole('link', { name: 'Create an account' }));

    expect(screen.getByRole('heading', { name: 'Create an account' })).toBeInTheDocument();
  });

  it('leads to resetting a forgotten password', async () => {
    server.use(sessionHandler(anonymousSession()));
    renderApp('/login');

    await userEvent
      .setup()
      .click(await screen.findByRole('link', { name: 'Forgot your password?' }));

    expect(screen.getByRole('heading', { name: 'Reset your password' })).toBeInTheDocument();
  });
});
