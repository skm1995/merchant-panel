import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http } from 'msw';
import { merchantSession, problem, sessionHandler } from '../test/api';
import { ORDERS_PATH, orderSummary, ordersHandler } from '../test/panel';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const FIRST = '11111111-1111-4111-8111-111111111111';
const SECOND = '22222222-2222-4222-8222-222222222222';

describe('OrdersPage', () => {
  it('lists orders and loads more on request', async () => {
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      ordersHandler([orderSummary(FIRST, '2026-10-25T12:00:00+00:00')], FIRST, {
        [FIRST]: { data: [orderSummary(SECOND, '2026-10-24T12:00:00+00:00')], next_cursor: null },
      }),
    );
    renderApp('/orders');

    const table = await screen.findByRole('table', { name: 'Orders' });
    expect(await within(table).findByRole('cell', { name: FIRST })).toBeInTheDocument();
    expect(within(table).getByRole('cell', { name: /^PLN\s123\.45$/ })).toBeInTheDocument();
    expect(within(table).getByRole('cell', { name: 'paid' })).toBeInTheDocument();

    await userEvent.setup().click(screen.getByRole('button', { name: 'Show more' }));

    expect(await within(table).findByRole('cell', { name: SECOND })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Show more' })).not.toBeInTheDocument();
  });

  it('clears the error once a retried page loads', async () => {
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      ordersHandler([orderSummary(FIRST, '2026-10-25T12:00:00+00:00')], FIRST, {
        [FIRST]: { data: [orderSummary(SECOND, '2026-10-24T12:00:00+00:00')], next_cursor: null },
      }),
    );
    renderApp('/orders');
    const user = userEvent.setup();
    const table = await screen.findByRole('table', { name: 'Orders' });
    await within(table).findByRole('cell', { name: FIRST });
    server.use(
      http.get(ORDERS_PATH, () => problem(503, 'Orders are unavailable.'), { once: true }),
    );

    await user.click(screen.getByRole('button', { name: 'Show more' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Orders are unavailable.');
    await user.click(screen.getByRole('button', { name: 'Show more' }));

    expect(await within(table).findByRole('cell', { name: SECOND })).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});
