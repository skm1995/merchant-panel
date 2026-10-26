import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { anonymousSession, problem, sessionHandler } from '../test/api';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const CONFIRMATIONS_PATH = '/api/v1/email-confirmations';
const TOKEN = 'emailed-token';

describe('ConfirmEmailPage', () => {
  it('confirms only after the user asks for it', async () => {
    const confirmations: unknown[] = [];
    server.use(
      sessionHandler(anonymousSession()),
      http.post(CONFIRMATIONS_PATH, async ({ request }) => {
        confirmations.push(await request.json());

        return new HttpResponse(null, { status: 204 });
      }),
    );
    renderApp(`/confirm?token=${TOKEN}`);

    const button = await screen.findByRole('button', { name: 'Confirm email' });
    expect(confirmations).toEqual([]);

    await userEvent.setup().click(button);

    expect(await screen.findByRole('status')).toHaveTextContent('Your email address is confirmed.');
    expect(confirmations).toEqual([{ token: TOKEN }]);
    expect(screen.getByRole('link', { name: 'Sign in' })).toBeInTheDocument();
  });

  it('explains an invalid or expired link', async () => {
    server.use(
      sessionHandler(anonymousSession()),
      http.post(CONFIRMATIONS_PATH, () =>
        problem(422, 'This confirmation link is invalid or has expired.'),
      ),
    );
    renderApp(`/confirm?token=${TOKEN}`);

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Confirm email' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'This confirmation link is invalid or has expired.',
    );
  });

  it('rejects a link without a token', async () => {
    server.use(sessionHandler(anonymousSession()));
    renderApp('/confirm');

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'This confirmation link is incomplete.',
    );
    expect(screen.queryByRole('button', { name: 'Confirm email' })).not.toBeInTheDocument();
  });
});
