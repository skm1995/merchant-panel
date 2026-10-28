import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import type { components } from '../api/schema';
import { merchantSession, problem, sessionHandler } from '../test/api';
import { renderApp } from '../test/render';
import { server } from '../test/server';

type Simulation = components['schemas']['Simulation'];

const SIMULATIONS_PATH = '/api/v1/panel/simulations';
const CURRENT_PATH = '/api/v1/panel/simulations/current';

function simulation(
  state: Simulation['state'],
  paid: number,
  check: Simulation['check'] = null,
): Simulation {
  return {
    id: 'a1b2',
    state,
    started_at: '2026-11-27T10:00:00+00:00',
    ends_at: '2026-11-27T10:01:00+00:00',
    buyers: 50,
    counters: { orders_placed: paid + 2, orders_paid: paid, sold_out: 12, failed_requests: 0 },
    products: [
      {
        product_id: 7,
        sku: 'bf-1',
        name: 'Black Friday TV',
        stock_at_start: 400,
        stock: 400 - paid,
        reserved: 2,
      },
    ],
    check,
  };
}

describe('BlackFridayPage', () => {
  it('shows the last simulation and confirms that nothing was oversold', async () => {
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      http.get(CURRENT_PATH, () =>
        HttpResponse.json(
          simulation('finished', 312, { consistent: true, units_sold: 431, units_paid: 431 }),
        ),
      ),
    );

    renderApp('/black-friday');

    expect(await screen.findByRole('status')).toHaveTextContent(
      '431 units left the shelves and 431 units were paid for',
    );
    expect(screen.getByRole('list', { name: 'Simulation counters' })).toHaveTextContent(
      'Orders paid312',
    );
  });

  it('starts a simulation and follows it until it finishes', async () => {
    let started = false;
    let polls = 0;
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      http.get(CURRENT_PATH, () => {
        if (!started) {
          return problem(404, 'No simulation has run yet.');
        }
        polls++;

        return HttpResponse.json(
          polls < 2
            ? simulation('running', 40)
            : simulation('finished', 90, { consistent: true, units_sold: 120, units_paid: 120 }),
        );
      }),
      http.post(SIMULATIONS_PATH, () => {
        started = true;

        return HttpResponse.json(simulation('running', 0), { status: 202 });
      }),
    );

    renderApp('/black-friday');
    await userEvent
      .setup()
      .click(await screen.findByRole('button', { name: 'Start Black Friday' }));

    expect(await screen.findByRole('list', { name: 'Simulation counters' })).toBeInTheDocument();
    expect(await screen.findByRole('status', {}, { timeout: 5000 })).toHaveTextContent(
      '120 units left the shelves and 120 units were paid for',
    );
    expect(screen.getByRole('button', { name: 'Start Black Friday' })).toBeEnabled();
  });

  it('explains why a simulation cannot start yet', async () => {
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      http.get(CURRENT_PATH, () => problem(404, 'No simulation has run yet.')),
      http.post(SIMULATIONS_PATH, () =>
        problem(
          409,
          'A simulation is running or has just finished; the next one can start in 120 seconds.',
        ),
      ),
    );

    renderApp('/black-friday');
    await userEvent
      .setup()
      .click(await screen.findByRole('button', { name: 'Start Black Friday' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'the next one can start in 120 seconds',
    );
  });

  it('shows the stock each product has left', async () => {
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      http.get(CURRENT_PATH, () =>
        HttpResponse.json(
          simulation('finished', 312, { consistent: true, units_sold: 312, units_paid: 312 }),
        ),
      ),
    );

    renderApp('/black-friday');
    const stock = await screen.findByRole('figure', { name: 'Stock left' });
    await userEvent.setup().click(within(stock).getByRole('button', { name: 'Show data' }));

    expect(within(stock).getByRole('row', { name: /Black Friday TV/ })).toHaveTextContent('400882');
  });
});
