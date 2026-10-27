import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { anonymousSession, SESSION_PATH, merchantSession } from '../test/api';
import { ordersHandler } from '../test/panel';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const EMAIL = 'merchant@example.com';

describe('PanelLayout', () => {
  it('sends anonymous visitors to sign in', async () => {
    server.use(http.get(SESSION_PATH, () => HttpResponse.json(anonymousSession())));

    renderApp('/');

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });

  it('opens the orders, names the merchant, and signs out', async () => {
    let session = merchantSession(EMAIL);
    server.use(
      http.get(SESSION_PATH, () => HttpResponse.json(session)),
      http.delete(SESSION_PATH, () => {
        session = anonymousSession();

        return new HttpResponse(null, { status: 204 });
      }),
      ordersHandler([]),
    );
    renderApp('/');

    expect(await screen.findByRole('heading', { name: 'Orders' })).toBeInTheDocument();
    expect(screen.getByText(`Signed in as ${EMAIL}`)).toBeInTheDocument();
    expect(screen.getByRole('navigation')).toHaveTextContent('OrdersProductsReports');

    await userEvent.setup().click(screen.getByRole('button', { name: 'Sign out' }));

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument();
  });
});
