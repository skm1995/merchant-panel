import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { anonymousSession, problem, sessionHandler } from '../test/api';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const RESETS_PATH = '/api/v1/password-resets';
const EMAIL = 'merchant@example.com';

describe('ForgotPasswordPage', () => {
  it('asks for a reset link without saying whether the address has an account', async () => {
    const requests: unknown[] = [];
    server.use(
      sessionHandler(anonymousSession()),
      http.post(RESETS_PATH, async ({ request }) => {
        requests.push(await request.json());

        return new HttpResponse(null, { status: 202 });
      }),
    );
    renderApp('/forgot-password');
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('Email'), EMAIL);
    await user.click(screen.getByRole('button', { name: 'Send reset link' }));

    expect(await screen.findByRole('status')).toHaveTextContent(
      `If ${EMAIL} belongs to a confirmed account, a reset link is on its way. It works for an hour.`,
    );
    expect(requests).toEqual([{ email: EMAIL }]);
  });

  it('explains a refused request', async () => {
    server.use(
      sessionHandler(anonymousSession()),
      http.post(RESETS_PATH, () => problem(429, 'Too many attempts. Try again in an hour.')),
    );
    renderApp('/forgot-password');
    const user = userEvent.setup();

    await user.type(await screen.findByLabelText('Email'), EMAIL);
    await user.click(screen.getByRole('button', { name: 'Send reset link' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Too many attempts. Try again in an hour.',
    );
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });
});
