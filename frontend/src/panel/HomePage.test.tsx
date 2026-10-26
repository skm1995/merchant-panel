import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { anonymousSession, SESSION_PATH, sessionHandler, signedInSession } from '../test/api';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const EMAIL = 'merchant@example.com';

describe('HomePage', () => {
  it('sends anonymous visitors to sign in', async () => {
    server.use(sessionHandler(anonymousSession()));

    renderApp('/');

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });

  it('greets the merchant and signs out', async () => {
    let session = signedInSession(EMAIL);
    server.use(
      http.get(SESSION_PATH, () => HttpResponse.json(session)),
      http.delete(SESSION_PATH, () => {
        session = anonymousSession();

        return new HttpResponse(null, { status: 204 });
      }),
    );
    renderApp('/');

    expect(await screen.findByText(`Signed in as ${EMAIL}`)).toBeInTheDocument();

    await userEvent.setup().click(screen.getByRole('button', { name: 'Sign out' }));

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });
});
