import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { anonymousSession, noAccessSession, SESSION_PATH, sessionHandler } from '../test/api';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const EMAIL = 'new-user@example.com';

describe('RequireMerchant', () => {
  it('sends anonymous visitors to sign in', async () => {
    server.use(sessionHandler(anonymousSession()));
    renderApp('/orders');

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });

  it('explains to a signed-in user without merchant access how to get it', async () => {
    server.use(
      sessionHandler(noAccessSession(EMAIL)),
      http.delete(SESSION_PATH, () => new HttpResponse(null, { status: 204 }), { once: true }),
    );
    renderApp('/orders');

    expect(await screen.findByRole('heading', { name: 'No panel access' })).toBeInTheDocument();
    expect(screen.getByText(new RegExp(EMAIL))).toBeInTheDocument();
    server.use(sessionHandler(anonymousSession()));

    await userEvent.setup().click(screen.getByRole('button', { name: 'Sign out' }));

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });
});
