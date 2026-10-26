import { render, screen } from '@testing-library/react';
import { Alert } from './Alert';

describe('Alert', () => {
  it('announces errors immediately', () => {
    render(<Alert tone="error">Something went wrong.</Alert>);

    expect(screen.getByRole('alert')).toHaveTextContent('Something went wrong.');
  });

  it('announces success politely', () => {
    render(<Alert tone="success">Saved.</Alert>);

    expect(screen.getByRole('status')).toHaveTextContent('Saved.');
  });
});
