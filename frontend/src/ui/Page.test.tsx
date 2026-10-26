import { render, screen } from '@testing-library/react';
import { Page } from './Page';

describe('Page', () => {
  it('titles its content', () => {
    render(
      <Page title="Products">
        <p>List</p>
      </Page>,
    );

    expect(screen.getByRole('heading', { level: 1, name: 'Products' })).toBeInTheDocument();
    expect(screen.getByText('List')).toBeInTheDocument();
  });
});
