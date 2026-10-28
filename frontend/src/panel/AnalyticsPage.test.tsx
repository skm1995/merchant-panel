import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import type { components } from '../api/schema';
import { merchantSession, problem, sessionHandler } from '../test/api';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const STOCK_OUTLOOK_PATH = '/api/v1/panel/reports/stock-outlook';
const ORDER_HEATMAP_PATH = '/api/v1/panel/reports/order-heatmap';
const MONTHLY_REVENUE_PATH = '/api/v1/panel/reports/monthly-revenue';

const STOCK_OUTLOOK: components['schemas']['StockOutlookReport'] = {
  days_measured: 28,
  products: [
    {
      product_id: 4,
      sku: 'SKU-4',
      name: 'Mug',
      available: 6,
      units_sold: 56,
      units_per_day: 2.0,
      days_left: 3,
    },
    {
      product_id: 6,
      sku: 'SKU-6',
      name: 'Bowl',
      available: 30,
      units_sold: 28,
      units_per_day: 1.0,
      days_left: 30,
    },
  ],
};

const ORDER_HEATMAP: components['schemas']['OrderHeatmapReport'] = {
  from: '2026-07-31',
  to: '2026-10-28',
  cells: [{ weekday: 1, hour: 9, orders: 12 }],
};

const MONTHLY_REVENUE: components['schemas']['MonthlyRevenueReport'] = {
  currency: 'PLN',
  months: [
    {
      month: '2026-10',
      revenue: { amount: '300000', currency: 'PLN' },
      previous_year_revenue: { amount: '200000', currency: 'PLN' },
      change_percent: 50.0,
    },
    {
      month: '2026-09',
      revenue: { amount: '100000', currency: 'PLN' },
      previous_year_revenue: { amount: '0', currency: 'PLN' },
      change_percent: null,
    },
  ],
};

function serveReports() {
  const requested: string[] = [];
  server.use(
    sessionHandler(merchantSession('merchant@example.com')),
    http.get(STOCK_OUTLOOK_PATH, () => HttpResponse.json(STOCK_OUTLOOK)),
    http.get(ORDER_HEATMAP_PATH, ({ request }) => {
      requested.push(new URL(request.url).search);

      return HttpResponse.json(ORDER_HEATMAP);
    }),
    http.get(MONTHLY_REVENUE_PATH, () => HttpResponse.json(MONTHLY_REVENUE)),
  );

  return requested;
}

describe('AnalyticsPage', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'], now: new Date('2026-10-28T12:00:00Z') });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('warns about products that sell out within a week', async () => {
    serveReports();
    renderApp('/analytics');

    const outlook = await screen.findByRole('figure', { name: 'Products that sell out first' });
    await userEvent.setup().click(within(outlook).getByRole('button', { name: 'Show data' }));

    const mug = within(outlook).getByRole('row', { name: /Mug/ });
    expect(within(mug).getByText('Low stock')).toBeInTheDocument();
    expect(within(mug).getByRole('cell', { name: '3' })).toBeInTheDocument();
    expect(within(outlook).getByRole('row', { name: /Bowl/ })).not.toHaveTextContent('Low stock');
  });

  it('shows when customers bought over the last 90 days', async () => {
    const requested = serveReports();
    renderApp('/analytics');

    expect(await screen.findByRole('img', { name: 'Monday 09:00: 12 orders' })).toBeInTheDocument();
    expect(requested).toEqual(['?from=2026-07-31&to=2026-10-28']);

    const heatmap = screen.getByRole('figure', { name: /When customers buy/ });
    await userEvent.setup().click(within(heatmap).getByRole('button', { name: 'Show data' }));
    expect(within(heatmap).getByRole('row', { name: 'Monday 09:00 12' })).toBeInTheDocument();
  });

  it('compares each month with the year before', async () => {
    serveReports();
    renderApp('/analytics');

    const monthly = await screen.findByRole('figure', {
      name: 'Monthly revenue against the year before',
    });
    await userEvent.setup().click(within(monthly).getByRole('button', { name: 'Show data' }));

    expect(within(monthly).getByRole('row', { name: /2026-10/ })).toHaveTextContent('+50.0%');
    expect(within(monthly).getByRole('row', { name: /2026-09/ })).toHaveTextContent('—');
  });

  it('explains a report that could not be loaded', async () => {
    serveReports();
    server.use(http.get(MONTHLY_REVENUE_PATH, () => problem(500, 'Something went wrong.')));
    renderApp('/analytics');

    expect(await screen.findByRole('alert')).toHaveTextContent('Something went wrong.');
  });
});
