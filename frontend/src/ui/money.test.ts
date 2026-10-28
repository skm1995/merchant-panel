import { toChartValue, formatMoney, toDecimalInput, toMinorUnits } from './money';

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

describe('toMinorUnits', () => {
  it('reads a decimal amount in the currency minor units', () => {
    expect(toMinorUnits('19.99', 'PLN')).toBe('1999');
    expect(toMinorUnits('20', 'PLN')).toBe('2000');
    expect(toMinorUnits('19.9', 'PLN')).toBe('1990');
    expect(toMinorUnits('1500', 'JPY')).toBe('1500');
  });

  it('rejects anything that is not a non-negative amount the currency can hold', () => {
    expect(toMinorUnits('19.999', 'PLN')).toBeNull();
    expect(toMinorUnits('12.5', 'JPY')).toBeNull();
    expect(toMinorUnits('-1', 'PLN')).toBeNull();
    expect(toMinorUnits('abc', 'PLN')).toBeNull();
    expect(toMinorUnits('', 'PLN')).toBeNull();
  });
});

describe('toDecimalInput', () => {
  it('shows minor units as a plain decimal for editing', () => {
    expect(toDecimalInput({ amount: '1999', currency: 'PLN' })).toBe('19.99');
    expect(toDecimalInput({ amount: '5', currency: 'PLN' })).toBe('0.05');
    expect(toDecimalInput({ amount: '1500', currency: 'JPY' })).toBe('1500');
  });
});

describe('toChartValue', () => {
  it('plots minor units as a number of major units', () => {
    expect(toChartValue({ amount: '1999', currency: 'PLN' })).toBe(19.99);
    expect(toChartValue({ amount: '5', currency: 'JPY' })).toBe(5);
  });
});
