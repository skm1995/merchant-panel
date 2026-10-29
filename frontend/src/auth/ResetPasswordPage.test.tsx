import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { anonymousSession, problem, sessionHandler } from '../test/api';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const CONFIRMATIONS_PATH = '/api/v1/password-reset-confirmations';
const TOKEN = 'emailed-reset-token';
const NEW_PASSWORD = 'new-password-456';

describe('ResetPasswordPage', () => {
  it('sets the new password with the token from the link and leads to sign in', async () => {
    const resets: unknown[] = [];
    server.use(
      sessionHandler(anonymousSession()),
      http.post(CONFIRMATIONS_PATH, async ({ request }) => {
        resets.push(await request.json());

        return new HttpResponse(null, { status: 204 });
      }),
    );
    renderApp(`/reset-password?token=${TOKEN}`);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('New password'), NEW_PASSWORD);
    await user.click(screen.getByRole('button', { name: 'Set new password' }));

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Your password is changed. Sign in with the new one.',
    );
    expect(resets).toEqual([{ token: TOKEN, password: NEW_PASSWORD }]);
    await user.click(screen.getByRole('link', { name: 'Sign in' }));
    expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });

  it('explains an expired link and offers a new one', async () => {
    server.use(
      sessionHandler(anonymousSession()),
      http.post(CONFIRMATIONS_PATH, () =>
        problem(422, 'This reset link is invalid or has expired. Ask for a new one.'),
      ),
    );
    renderApp(`/reset-password?token=${TOKEN}`);
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('New password'), NEW_PASSWORD);
    await user.click(screen.getByRole('button', { name: 'Set new password' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'This reset link is invalid or has expired. Ask for a new one.',
    );
    expect(screen.getByRole('link', { name: 'Ask for a new link' })).toBeInTheDocument();
  });

  it('rejects a link without a token', async () => {
    server.use(sessionHandler(anonymousSession()));
    renderApp('/reset-password');

    expect(await screen.findByRole('alert')).toHaveTextContent('This reset link is incomplete.');
    expect(screen.queryByLabelText('New password')).not.toBeInTheDocument();
  });
});
