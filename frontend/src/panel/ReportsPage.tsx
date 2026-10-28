import { useState, type SubmitEvent } from 'react';
import { apiClient } from '../api/client';
import { problemMessage } from '../api/problem';
import type { components } from '../api/schema';
import { Alert } from '../ui/Alert';
import { BarChart } from '../ui/BarChart';
import { Button } from '../ui/Button';
import { formatCompactAmount, formatWholeAmount } from '../ui/chartFormat';
import { ChartPanel } from '../ui/ChartPanel';
import { DataTable, type Column } from '../ui/DataTable';
import { LineChart } from '../ui/LineChart';
import { formatMoney, toChartValue } from '../ui/money';
import { Page } from '../ui/Page';
import { TextField } from '../ui/TextField';

type DailyRevenueReport = components['schemas']['DailyRevenueReport'];
type TopProductsReport = components['schemas']['TopProductsReport'];
type Day = DailyRevenueReport['days'][number];
type RankedProduct = TopProductsReport['products'][number];

const DAY_COLUMNS: Column<Day>[] = [
  { header: 'Day', cell: (day) => day.day },
  { header: 'Orders', cell: (day) => String(day.orders) },
  { header: 'Revenue', cell: (day) => formatMoney(day.revenue) },
  { header: 'Running total', cell: (day) => formatMoney(day.running_total) },
];

const RUNNING_TOTAL_COLUMNS: Column<Day>[] = [
  { header: 'Day', cell: (day) => day.day },
  { header: 'Running total', cell: (day) => formatMoney(day.running_total) },
];

const PRODUCT_COLUMNS: Column<RankedProduct>[] = [
  { header: 'Rank', cell: (product) => String(product.rank) },
  { header: 'Name', cell: (product) => product.name },
  { header: 'SKU', cell: (product) => product.sku },
  { header: 'Units sold', cell: (product) => String(product.units_sold) },
  { header: 'Revenue', cell: (product) => formatMoney(product.revenue) },
];

interface Reports {
  revenue: DailyRevenueReport;
  products: TopProductsReport;
}

export function ReportsPage() {
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [reports, setReports] = useState<Reports | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function show(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    const period = { params: { query: { from, to } } };
    const [revenue, products] = await Promise.all([
      apiClient.GET('/api/v1/panel/reports/daily-revenue', period),
      apiClient.GET('/api/v1/panel/reports/top-products', period),
    ]);
    setBusy(false);

    if (revenue.data === undefined || products.data === undefined) {
      setReports(null);
      setError(problemMessage(revenue.error ?? products.error));

      return;
    }

    setError(null);
    setReports({ revenue: revenue.data, products: products.data });
  }

  return (
    <Page title="Reports">
      {error !== null && <Alert tone="error">{error}</Alert>}
      <form onSubmit={(event) => void show(event)}>
        <TextField label="From" type="date" value={from} onChange={setFrom} />
        <TextField label="To" type="date" value={to} onChange={setTo} />
        <Button type="submit" busy={busy}>
          Show reports
        </Button>
      </form>
      {reports !== null && (
        <>
          <ChartPanel
            title="Daily revenue"
            howItsComputed="SQL groups the paid orders of the period by day with GROUP BY and adds up their totals; days without paid orders are left out."
            data={
              <DataTable
                caption="Daily revenue"
                columns={DAY_COLUMNS}
                rows={reports.revenue.days}
                rowKey={(day) => day.day}
              />
            }
          >
            <BarChart
              data={reports.revenue.days.map((day) => ({
                label: day.day,
                value: toChartValue(day.revenue),
              }))}
              format={formatWholeAmount(reports.revenue.currency)}
              formatAxis={formatCompactAmount(reports.revenue.currency)}
            />
          </ChartPanel>
          <ChartPanel
            title="Running total"
            howItsComputed="PHP adds each day's revenue to the total of the days before it, with Money, on the small result of the daily revenue query."
            data={
              <DataTable
                caption="Running total"
                columns={RUNNING_TOTAL_COLUMNS}
                rows={reports.revenue.days}
                rowKey={(day) => day.day}
              />
            }
          >
            <LineChart
              data={reports.revenue.days.map((day) => ({
                label: day.day,
                total: toChartValue(day.running_total),
              }))}
              series={[{ key: 'total', name: 'Running total' }]}
              format={formatWholeAmount(reports.revenue.currency)}
              formatAxis={formatCompactAmount(reports.revenue.currency)}
            />
          </ChartPanel>
          <ChartPanel
            title="Top products"
            howItsComputed="SQL adds up units and revenue per product from the lines of paid orders, sorts by revenue, and keeps the first ones with LIMIT; equal revenue is ordered by product id."
            data={
              <DataTable
                caption="Top products"
                columns={PRODUCT_COLUMNS}
                rows={reports.products.products}
                rowKey={(product) => String(product.product_id)}
              />
            }
          >
            <BarChart
              horizontal
              data={reports.products.products.map((product) => ({
                label: product.name,
                value: toChartValue(product.revenue),
              }))}
              format={formatWholeAmount(reports.products.currency)}
              formatAxis={formatCompactAmount(reports.products.currency)}
            />
          </ChartPanel>
        </>
      )}
    </Page>
  );
}
