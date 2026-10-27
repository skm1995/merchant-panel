import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import type { components } from '../api/schema';
import { problem, sessionHandler, merchantSession } from '../test/api';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const DAILY_REVENUE_PATH = '/api/v1/panel/reports/daily-revenue';
const TOP_PRODUCTS_PATH = '/api/v1/panel/reports/top-products';

const DAILY_REVENUE: components['schemas']['DailyRevenueReport'] = {
  from: '2026-10-01',
  to: '2026-10-07',
  currency: 'PLN',
  days: [
    {
      day: '2026-10-02',
      orders: 3,
      revenue: { amount: '30000', currency: 'PLN' },
      running_total: { amount: '30000', currency: 'PLN' },
    },
  ],
};

const TOP_PRODUCTS: components['schemas']['TopProductsReport'] = {
  from: '2026-10-01',
  to: '2026-10-07',
  currency: 'PLN',
  products: [
    {
      rank: 1,
      product_id: 7,
      sku: 'SKU-7',
      name: 'Mug',
      units_sold: 4,
      revenue: { amount: '8000', currency: 'PLN' },
    },
  ],
};

async function showReports(from: string, to: string) {
  renderApp('/reports');
  const user = userEvent.setup();
  const fromField = await screen.findByLabelText('From');
  await user.clear(fromField);
  await user.type(fromField, from);
  const toField = screen.getByLabelText('To');
  await user.clear(toField);
  await user.type(toField, to);
  await user.click(screen.getByRole('button', { name: 'Show reports' }));
}

describe('ReportsPage', () => {
  it('shows revenue and top products for the chosen period', async () => {
    const requested: string[] = [];
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      http.get(DAILY_REVENUE_PATH, ({ request }) => {
        requested.push(new URL(request.url).search);

        return HttpResponse.json(DAILY_REVENUE);
      }),
      http.get(TOP_PRODUCTS_PATH, () => HttpResponse.json(TOP_PRODUCTS)),
    );

    await showReports('2026-10-01', '2026-10-07');

    const revenue = await screen.findByRole('table', { name: 'Daily revenue' });
    expect(within(revenue).getAllByRole('cell', { name: /^PLN\s300\.00$/ })).toHaveLength(2);
    const products = screen.getByRole('table', { name: 'Top products' });
    expect(within(products).getByRole('cell', { name: 'Mug' })).toBeInTheDocument();
    expect(requested).toContain('?from=2026-10-01&to=2026-10-07');
  });

  it('explains an invalid period', async () => {
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      http.get(DAILY_REVENUE_PATH, () =>
        problem(400, 'The report period must cover 1 to 366 days.'),
      ),
      http.get(TOP_PRODUCTS_PATH, () =>
        problem(400, 'The report period must cover 1 to 366 days.'),
      ),
    );

    await showReports('2026-10-07', '2026-10-01');

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The report period must cover 1 to 366 days.',
    );
  });
});
