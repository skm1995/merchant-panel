const LOCALE = 'en-US';

/**
 * Formats axis values in whole units of the currency, such as "PLN 1,235": an axis needs the
 * magnitude, while tables and tooltips of the reports show exact amounts.
 */
export function formatWholeAmount(currency: string): (value: number) => string {
  const format = new Intl.NumberFormat(LOCALE, {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  });

  return (value) => format.format(value);
}

export function formatDays(days: number): string {
  return `${String(days)} ${days === 1 ? 'day' : 'days'}`;
}

/**
 * Adapts a number format to the chart library's tooltip, which hands values over as numbers
 * or numeric strings.
 */
export function tooltipValue(format: (value: number) => string): (value: unknown) => string {
  return (value) => format(Number(value));
}
