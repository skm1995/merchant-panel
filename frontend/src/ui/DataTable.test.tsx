import { render, screen, within } from '@testing-library/react';
import { DataTable } from './DataTable';

interface Row {
  id: string;
  name: string;
}

const COLUMNS = [
  { header: 'Id', cell: (row: Row) => row.id },
  { header: 'Name', cell: (row: Row) => row.name },
];

describe('DataTable', () => {
  it('shows a row per item under labelled columns', () => {
    render(
      <DataTable
        caption="Products"
        columns={COLUMNS}
        rows={[{ id: '1', name: 'Mug' }]}
        rowKey={(row) => row.id}
      />,
    );

    const table = screen.getByRole('table', { name: 'Products' });
    expect(
      within(table)
        .getAllByRole('columnheader')
        .map((cell) => cell.textContent),
    ).toEqual(['Id', 'Name']);
    expect(within(table).getByRole('cell', { name: 'Mug' })).toBeInTheDocument();
  });

  it('says when there is nothing to show', () => {
    render(<DataTable caption="Products" columns={COLUMNS} rows={[]} rowKey={(row) => row.id} />);

    expect(screen.getByText('Nothing to show yet.')).toBeInTheDocument();
  });
});
