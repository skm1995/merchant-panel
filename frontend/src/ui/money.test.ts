import { formatMoney } from './money';

describe('formatMoney', () => {
  it('formats minor units without floating-point rounding', () => {
    expect(formatMoney({ amount: '123456789012345678', currency: 'PLN' }, 'en-US')).toBe(
      'PLN\u00a01,234,567,890,123,456.78',
    );
  });

  it('respects currencies without minor units', () => {
    expect(formatMoney({ amount: '1500', currency: 'JPY' }, 'en-US')).toBe('¥1,500');
  });

  it('formats negative amounts', () => {
    expect(formatMoney({ amount: '-5', currency: 'EUR' }, 'en-US')).toBe('-€0.05');
  });
});
