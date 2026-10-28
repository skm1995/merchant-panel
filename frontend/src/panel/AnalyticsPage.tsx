import { useEffect, useState } from 'react';
import { apiClient } from '../api/client';
import { problemMessage } from '../api/problem';
import type { components } from '../api/schema';
import { Alert } from '../ui/Alert';
import { BarChart } from '../ui/BarChart';
import { formatDays, formatWholeAmount } from '../ui/chartFormat';
import { ChartPanel } from '../ui/ChartPanel';
import { DataTable, type Column } from '../ui/DataTable';
import { Heatmap, type HeatmapCell } from '../ui/Heatmap';
import { LineChart } from '../ui/LineChart';
import { formatMoney, toChartValue } from '../ui/money';
import { Page } from '../ui/Page';
import styles from './AnalyticsPage.module.css';

type StockOutlookReport = components['schemas']['StockOutlookReport'];
type OrderHeatmapReport = components['schemas']['OrderHeatmapReport'];
type MonthlyRevenueReport = components['schemas']['MonthlyRevenueReport'];
type ProductOutlook = StockOutlookReport['products'][number];
type Month = MonthlyRevenueReport['months'][number];

interface Reports {
  outlook: StockOutlookReport;
  heatmap: OrderHeatmapReport;
  monthly: MonthlyRevenueReport;
}

const HEATMAP_DAYS = 90;
const LOW_STOCK_DAYS = 7;
const DAY_IN_MILLISECONDS = 24 * 60 * 60 * 1000;
const NO_CHANGE = '—';
const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function isoDate(date: Date): string {
  return date.toISOString().slice(0, 10);
}

function formatChange(percent: number | null): string {
  if (percent === null) {
    return NO_CHANGE;
  }

  return `${percent > 0 ? '+' : ''}${percent.toFixed(1)}%`;
}

const OUTLOOK_COLUMNS: Column<ProductOutlook>[] = [
  { header: 'Product', cell: (product) => product.name },
  { header: 'SKU', cell: (product) => product.sku },
  { header: 'Available', cell: (product) => String(product.available) },
  { header: 'Sold in 28 days', cell: (product) => String(product.units_sold) },
  { header: 'Per day', cell: (product) => product.units_per_day.toFixed(2) },
  { header: 'Days left', cell: (product) => String(product.days_left) },
  {
    header: 'Status',
    cell: (product) =>
      product.days_left < LOW_STOCK_DAYS ? (
        <span className={styles.lowStock}>
          <span aria-hidden="true">⚠ </span>
          <span>Low stock</span>
        </span>
      ) : (
        'Enough'
      ),
  },
];

const HEATMAP_COLUMNS: Column<HeatmapCell>[] = [
  { header: 'Weekday', cell: (cell) => WEEKDAYS[cell.weekday - 1] },
  { header: 'Hour (UTC)', cell: (cell) => `${String(cell.hour).padStart(2, '0')}:00` },
  { header: 'Orders', cell: (cell) => String(cell.orders) },
];

const MONTH_COLUMNS: Column<Month>[] = [
  { header: 'Month', cell: (month) => month.month },
  { header: 'Revenue', cell: (month) => formatMoney(month.revenue) },
  { header: 'A year before', cell: (month) => formatMoney(month.previous_year_revenue) },
  { header: 'Change', cell: (month) => formatChange(month.change_percent) },
];

/**
 * Longer-term trends: which products sell out first, when customers buy, and how each month
 * compares with the year before.
 */
export function AnalyticsPage() {
  const [reports, setReports] = useState<Reports | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const today = new Date();
    const from = isoDate(new Date(today.getTime() - (HEATMAP_DAYS - 1) * DAY_IN_MILLISECONDS));

    void Promise.all([
      apiClient.GET('/api/v1/panel/reports/stock-outlook'),
      apiClient.GET('/api/v1/panel/reports/order-heatmap', {
        params: { query: { from, to: isoDate(today) } },
      }),
      apiClient.GET('/api/v1/panel/reports/monthly-revenue'),
    ]).then(([outlook, heatmap, monthly]) => {
      if (outlook.data === undefined || heatmap.data === undefined || monthly.data === undefined) {
        setError(problemMessage(outlook.error ?? heatmap.error ?? monthly.error));

        return;
      }

      setReports({ outlook: outlook.data, heatmap: heatmap.data, monthly: monthly.data });
    });
  }, []);

  return (
    <Page title="Analytics">
      {error !== null && <Alert tone="error">{error}</Alert>}
      {reports === null && error === null && <p>Loading reports…</p>}
      {reports !== null && (
        <div className={styles.reports}>
          <ChartPanel
            title="Products that sell out first"
            howItsComputed={`SQL adds up each product's units sold in the last ${String(reports.outlook.days_measured)} full days from the daily sales summary, joins the available stock, and sorts by stock per unit sold with LIMIT. PHP turns the result into units per day and whole days of stock left.`}
            data={
              <DataTable
                caption="Products that sell out first"
                columns={OUTLOOK_COLUMNS}
                rows={reports.outlook.products}
                rowKey={(product) => String(product.product_id)}
              />
            }
          >
            <BarChart
              horizontal
              data={reports.outlook.products.map((product) => ({
                label: product.name,
                value: product.days_left,
              }))}
              format={formatDays}
            />
          </ChartPanel>
          <ChartPanel
            title={`When customers buy: paid orders of the last ${String(HEATMAP_DAYS)} days`}
            howItsComputed="SQL counts paid orders per WEEKDAY() and HOUR() of their time in UTC. It reads only the index on status and creation time, never the order rows themselves."
            data={
              <DataTable
                caption="Paid orders by weekday and hour"
                columns={HEATMAP_COLUMNS}
                rows={reports.heatmap.cells}
                rowKey={(cell) => `${String(cell.weekday)}-${String(cell.hour)}`}
              />
            }
          >
            <Heatmap title="Paid orders by weekday and hour (UTC)" cells={reports.heatmap.cells} />
          </ChartPanel>
          <ChartPanel
            title="Monthly revenue against the year before"
            howItsComputed="SQL adds up the daily sales summary per month for the last 24 months. PHP sets each of the last 12 months next to the same month a year earlier and computes the change with Money."
            data={
              <DataTable
                caption="Monthly revenue against the year before"
                columns={MONTH_COLUMNS}
                rows={reports.monthly.months}
                rowKey={(month) => month.month}
              />
            }
          >
            <LineChart
              data={reports.monthly.months.map((month) => ({
                label: month.month,
                revenue: toChartValue(month.revenue),
                previous: toChartValue(month.previous_year_revenue),
              }))}
              series={[
                { key: 'revenue', name: 'This year' },
                { key: 'previous', name: 'A year before' },
              ]}
              format={formatWholeAmount(reports.monthly.currency)}
            />
          </ChartPanel>
        </div>
      )}
    </Page>
  );
}
