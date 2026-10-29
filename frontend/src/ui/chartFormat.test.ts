import {
  categoryWidth,
  fitLabel,
  formatCompactAmount,
  formatDays,
  formatWholeAmount,
  tooltipValue,
} from './chartFormat';

describe('chart formats', () => {
  it('shows axis amounts in whole units of the currency', () => {
    expect(formatWholeAmount('PLN')(1234.56)).toBe('PLN 1,235');
  });

  it('shortens large axis amounts', () => {
    expect(formatCompactAmount('PLN')(140_000_000)).toBe('PLN\u00a0140M');
    expect(formatCompactAmount('PLN')(2500)).toBe('PLN\u00a02.5K');
  });

  it('counts days', () => {
    expect(formatDays(1)).toBe('1 day');
    expect(formatDays(30)).toBe('30 days');
  });

  it('formats the values the chart library hands to a tooltip', () => {
    const format = tooltipValue(formatDays);

    expect(format(3)).toBe('3 days');
    expect(format('12')).toBe('12 days');
  });

  it('sizes the category axis to its longest name, within limits', () => {
    expect(categoryWidth(['TV'])).toBe(60);
    expect(categoryWidth(['Black Friday Coffee Machine', 'TV'])).toBe(215);
    expect(categoryWidth(['A product name far too long to fit beside any chart'])).toBe(220);
    expect(categoryWidth([])).toBe(60);
  });

  it('keeps names that fit and shortens the others with an ellipsis', () => {
    expect(fitLabel('Black Friday TV', 220)).toBe('Black Friday TV');
    expect(fitLabel('A product name far too long to fit beside any chart', 220)).toBe(
      'A product name far too lon…',
    );
  });
});
