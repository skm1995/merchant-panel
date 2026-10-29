import { useCallback, useEffect, useRef, useState } from 'react';
import { apiClient } from '../api/client';
import { problemMessage } from '../api/problem';
import type { components } from '../api/schema';
import { Alert } from '../ui/Alert';
import { BarChart } from '../ui/BarChart';
import { Button } from '../ui/Button';
import { ChartPanel } from '../ui/ChartPanel';
import { DataTable, type Column } from '../ui/DataTable';
import { LineChart } from '../ui/LineChart';
import { Page } from '../ui/Page';
import { StatList } from '../ui/StatList';
import styles from './BlackFridayPage.module.css';

type Simulation = components['schemas']['Simulation'];
type SimulationProduct = Simulation['products'][number];

interface Pace {
  second: number;
  orders: number;
}

const POLL_MILLISECONDS = 1000;
const NOT_FOUND = 404;
const NUMBER = new Intl.NumberFormat('en-US');

const PRODUCT_COLUMNS: Column<SimulationProduct>[] = [
  { header: 'Product', cell: (product) => product.name },
  { header: 'At start', cell: (product) => String(product.stock_at_start) },
  { header: 'Left', cell: (product) => String(product.stock) },
  { header: 'Reserved', cell: (product) => String(product.reserved) },
];

const PACE_COLUMNS: Column<Pace>[] = [
  { header: 'Second', cell: (pace) => String(pace.second) },
  { header: 'Orders placed', cell: (pace) => String(pace.orders) },
];

function formatCount(value: number): string {
  return NUMBER.format(value);
}

/**
 * Starts a Black Friday simulation and follows it live: simulated buyers check out, pay, and
 * run the shelves empty through the real API, and the end compares the stock taken with the
 * orders paid.
 */
export function BlackFridayPage() {
  const [simulation, setSimulation] = useState<Simulation | null>(null);
  const [pace, setPace] = useState<Pace[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const lastPlaced = useRef<number | null>(null);

  const load = useCallback(async () => {
    const {
      data,
      error: problem,
      response,
    } = await apiClient.GET('/api/v1/panel/simulations/current');

    if (data !== undefined) {
      if (data.state === 'running' && lastPlaced.current !== null) {
        const placed = data.counters.orders_placed - lastPlaced.current;
        setPace((points) => [
          ...points,
          { second: points.length + 1, orders: Math.max(0, placed) },
        ]);
      }
      lastPlaced.current = data.counters.orders_placed;
      setSimulation(data);
    } else if (response.status !== NOT_FOUND) {
      setError(problemMessage(problem));
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const running = simulation?.state === 'running';

  useEffect(() => {
    if (!running) {
      return undefined;
    }

    const timer = setInterval(() => void load(), POLL_MILLISECONDS);

    return () => {
      clearInterval(timer);
    };
  }, [running, load]);

  async function start() {
    setBusy(true);
    setError(null);
    const { data, error: problem } = await apiClient.POST('/api/v1/panel/simulations');
    setBusy(false);

    if (data === undefined) {
      setError(problemMessage(problem));

      return;
    }

    setPace([]);
    lastPlaced.current = data.counters.orders_placed;
    setSimulation(data);
  }

  return (
    <Page title="Black Friday">
      <p className={styles.intro}>
        Simulated buyers shop through the public API for a minute, as customers would on Black
        Friday: they check out, pay, and empty the shelves. Afterwards the panel compares the stock
        that left the shelves with the orders that were paid.
      </p>
      <div>
        <Button onClick={() => void start()} busy={busy || running}>
          Start Black Friday
        </Button>
      </div>
      {error !== null && <Alert tone="error">{error}</Alert>}
      {simulation !== null && (
        <>
          <StatList
            label="Simulation counters"
            stats={[
              { label: 'Buyers', value: formatCount(simulation.buyers) },
              { label: 'Orders placed', value: formatCount(simulation.counters.orders_placed) },
              { label: 'Orders paid', value: formatCount(simulation.counters.orders_paid) },
              { label: 'Sold out', value: formatCount(simulation.counters.sold_out) },
              {
                label: 'Failed requests',
                value: formatCount(simulation.counters.failed_requests),
              },
            ]}
          />
          {simulation.check !== null &&
            (simulation.check.consistent ? (
              <Alert tone="success">
                Consistent: {formatCount(simulation.check.units_sold)} units left the shelves and{' '}
                {formatCount(simulation.check.units_paid)} units were paid for; no product was sold
                twice or below zero.
              </Alert>
            ) : (
              <Alert tone="error">
                Inconsistent: {formatCount(simulation.check.units_sold)} units left the shelves, but{' '}
                {formatCount(simulation.check.units_paid)} units were paid for.
              </Alert>
            ))}
          {pace.length > 0 && (
            <ChartPanel
              title="Orders per second"
              howItsComputed="The panel asks for the simulation's counters every second and plots how many orders were placed since the last answer."
              data={
                <DataTable
                  caption="Orders per second"
                  columns={PACE_COLUMNS}
                  rows={pace}
                  rowKey={(point) => String(point.second)}
                />
              }
            >
              <LineChart
                data={pace.map((point) => ({
                  label: String(point.second),
                  orders: point.orders,
                }))}
                series={[{ key: 'orders', name: 'Orders per second' }]}
                format={formatCount}
              />
            </ChartPanel>
          )}
          <ChartPanel
            title="Stock left"
            howItsComputed="Checkouts reserve stock under row locks with a conditional update, and payments turn reservations into sold stock, so stock never goes below zero however many buyers arrive at once."
            data={
              <DataTable
                caption="Stock left"
                columns={PRODUCT_COLUMNS}
                rows={simulation.products}
                rowKey={(product) => String(product.product_id)}
              />
            }
          >
            <BarChart
              horizontal
              data={simulation.products.map((product) => ({
                label: product.name,
                value: product.stock,
              }))}
              format={formatCount}
            />
          </ChartPanel>
        </>
      )}
    </Page>
  );
}
