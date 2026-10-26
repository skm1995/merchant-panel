import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { anonymousSession, problem, sessionHandler } from '../test/api';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const REGISTRATIONS_PATH = '/api/v1/registrations';
const EMAIL = 'new-merchant@example.com';
const PASSWORD = 'merchant-password';

async function register() {
  renderApp('/register');
  const user = userEvent.setup();

  await user.type(await screen.findByLabelText('Email'), EMAIL);
  await user.type(screen.getByLabelText('Password'), PASSWORD);
  await user.click(screen.getByRole('button', { name: 'Create account' }));
}

describe('RegisterPage', () => {
  it('asks the user to confirm the email address', async () => {
    let submitted: unknown = null;
    server.use(
      sessionHandler(anonymousSession()),
      http.post(REGISTRATIONS_PATH, async ({ request }) => {
        submitted = await request.json();

        return new HttpResponse(null, { status: 202 });
      }),
    );

    await register();

    expect(await screen.findByRole('status')).toHaveTextContent(
      `We sent a confirmation link to ${EMAIL}.`,
    );
    expect(submitted).toEqual({ email: EMAIL, password: PASSWORD });
  });

  it('explains why the registration was rejected', async () => {
    server.use(
      sessionHandler(anonymousSession()),
      http.post(REGISTRATIONS_PATH, () =>
        problem(422, 'Password must contain at least 8 characters.'),
      ),
    );

    await register();

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Password must contain at least 8 characters.',
    );
  });
});
