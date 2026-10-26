import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { TextField } from './TextField';

describe('TextField', () => {
  it('is labelled and reports what the user types', async () => {
    const onChange = vi.fn();
    render(<TextField label="Email" type="email" value="" onChange={onChange} />);

    await userEvent.setup().type(screen.getByLabelText('Email'), 'a');

    expect(screen.getByLabelText('Email')).toHaveAttribute('type', 'email');
    expect(onChange).toHaveBeenCalledWith('a');
  });

  it('describes its error to assistive technology', () => {
    render(
      <TextField label="Password" type="password" value="" onChange={vi.fn()} error="Too short." />,
    );

    const input = screen.getByLabelText('Password');
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAccessibleDescription('Too short.');
  });
});
