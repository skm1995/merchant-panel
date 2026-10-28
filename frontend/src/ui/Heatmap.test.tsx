import { render, screen } from '@testing-library/react';
import { Heatmap } from './Heatmap';

describe('Heatmap', () => {
  it('labels every weekday and hour with its count, including the empty ones', () => {
    render(
      <Heatmap
        title="Paid orders by weekday and hour (UTC)"
        cells={[
          { weekday: 1, hour: 9, orders: 2 },
          { weekday: 7, hour: 18, orders: 1 },
        ]}
      />,
    );

    expect(screen.getAllByRole('img', { name: /: \d+ orders?$/ })).toHaveLength(7 * 24);
    expect(screen.getByRole('img', { name: 'Monday 09:00: 2 orders' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Sunday 18:00: 1 order' })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Tuesday 03:00: 0 orders' })).toBeInTheDocument();
  });

  it('shows a scale from fewer to more orders', () => {
    render(<Heatmap title="Orders" cells={[]} />);

    expect(screen.getByText('Fewer')).toBeInTheDocument();
    expect(screen.getByText('More')).toBeInTheDocument();
  });
});
