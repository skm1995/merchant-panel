import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ChartPanel } from './ChartPanel';

describe('ChartPanel', () => {
  it('names the chart and keeps its data in a table behind a toggle', async () => {
    render(
      <ChartPanel
        title="Daily revenue"
        howItsComputed="SQL groups paid orders by day."
        data={<table aria-label="Daily revenue data" />}
      >
        <svg />
      </ChartPanel>,
    );
    const user = userEvent.setup();

    expect(screen.getByRole('figure', { name: 'Daily revenue' })).toBeInTheDocument();
    expect(screen.queryByRole('table', { name: 'Daily revenue data' })).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Show data' }));
    expect(screen.getByRole('table', { name: 'Daily revenue data' })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Hide data' }));
    expect(screen.queryByRole('table', { name: 'Daily revenue data' })).not.toBeInTheDocument();
  });

  it('explains how the numbers are computed on request', async () => {
    render(
      <ChartPanel title="Top products" howItsComputed="SQL sums revenue per product." data={null}>
        <svg />
      </ChartPanel>,
    );

    await userEvent.setup().click(screen.getByText("How it's computed"));

    expect(screen.getByText('SQL sums revenue per product.')).toBeVisible();
  });
});
