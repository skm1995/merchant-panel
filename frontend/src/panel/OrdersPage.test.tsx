import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { sessionHandler, signedInSession } from '../test/api';
import { orderSummary, ordersHandler } from '../test/panel';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const FIRST = '11111111-1111-4111-8111-111111111111';
const SECOND = '22222222-2222-4222-8222-222222222222';

describe('OrdersPage', () => {
  it('lists orders and loads more on request', async () => {
    server.use(
      sessionHandler(signedInSession('merchant@example.com')),
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
});
