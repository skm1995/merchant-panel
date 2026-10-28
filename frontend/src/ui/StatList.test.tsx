import { render, screen } from '@testing-library/react';
import { StatList } from './StatList';

describe('StatList', () => {
  it('pairs every value with its label', () => {
    render(
      <StatList
        label="Simulation counters"
        stats={[
          { label: 'Orders placed', value: '1,204' },
          { label: 'Sold out', value: '87' },
        ]}
      />,
    );

    const list = screen.getByRole('list', { name: 'Simulation counters' });
    expect(list).toHaveTextContent('Orders placed1,204');
    expect(list).toHaveTextContent('Sold out87');
  });
});
