const LOCALE = 'en-US';

// The axis font's average character width, to size the category axis to its longest name.
const CHARACTER_WIDTH = 7.5;
const TICK_SPACE = 12;
const MIN_CATEGORY_WIDTH = 60;
const MAX_CATEGORY_WIDTH = 220;
const ELLIPSIS = '…';

/**
 * Wide enough for the longest category name, up to a limit, so names stay on one line.
 */
export function categoryWidth(labels: string[]): number {
  const longest = Math.max(0, ...labels.map((label) => label.length));

  return Math.min(
    MAX_CATEGORY_WIDTH,
    Math.max(MIN_CATEGORY_WIDTH, Math.ceil(longest * CHARACTER_WIDTH) + TICK_SPACE),
  );
}

/**
 * Shortens a name that does not fit the axis and ends it with an ellipsis.
 */
export function fitLabel(label: string, width: number): string {
  const characters = Math.floor((width - TICK_SPACE) / CHARACTER_WIDTH);

  return label.length <= characters ? label : `${label.slice(0, characters - 1)}${ELLIPSIS}`;
}

/**
 * Formats values in whole units of the currency, such as "PLN 1,235", for tooltips; the
 * reports' tables show the exact amounts.
 */
export function formatWholeAmount(currency: string): (value: number) => string {
  const format = new Intl.NumberFormat(LOCALE, {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  });

  return (value) => format.format(value);
}

/**
 * Formats axis values compactly, such as "PLN 140M", so long amounts fit beside the chart.
 */
export function formatCompactAmount(currency: string): (value: number) => string {
  const format = new Intl.NumberFormat(LOCALE, {
    style: 'currency',
    currency,
    notation: 'compact',
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
