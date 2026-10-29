import type { components } from '../api/schema';

type Money = components['schemas']['Money'];

const DEFAULT_LOCALE = 'en-US';
const MINUS = '-';
const ZERO = '0';
const DECIMAL_POINT = '.';
const LEADING_ZEROS = /^0+(?=\d)/;

/**
 * How many decimal places amounts in the currency may have.
 */
export function decimalPlaces(currency: string): number {
  return (
    new Intl.NumberFormat(DEFAULT_LOCALE, { style: 'currency', currency }).resolvedOptions()
      .maximumFractionDigits ?? 0
  );
}

/**
 * The amount as a plain decimal string, such as "-19.99" for -1999 minor units of PLN.
 */
function toDecimalString(money: Money): string {
  const digitsAfterPoint = decimalPlaces(money.currency);
  const negative = money.amount.startsWith(MINUS);
  const digits = (negative ? money.amount.slice(MINUS.length) : money.amount).padStart(
    digitsAfterPoint + 1,
    ZERO,
  );
  const units = digits.slice(0, digits.length - digitsAfterPoint);
  const decimal =
    digitsAfterPoint === 0
      ? units
      : `${units}${DECIMAL_POINT}${digits.slice(digits.length - digitsAfterPoint)}`;

  return negative ? `${MINUS}${decimal}` : decimal;
}

/**
 * Formats an API amount in minor units. The amount is turned into a decimal string and
 * formatted as a string, so even amounts beyond the safe integer range keep every digit.
 */
export function formatMoney(money: Money, locale: string = DEFAULT_LOCALE): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency: money.currency }).format(
    toDecimalString(money) as Intl.StringNumericLiteral,
  );
}

/**
 * The amount as the user edits it, such as "19.99".
 */
export function toDecimalInput(money: Money): string {
  return toDecimalString(money);
}

/**
 * Reads a non-negative decimal amount typed by the user as minor units of the currency, or
 * null when it is not one or has more decimal places than the currency allows.
 */
export function toMinorUnits(text: string, currency: string): string | null {
  const digitsAfterPoint = decimalPlaces(currency);
  const pattern =
    digitsAfterPoint === 0
      ? /^(\d+)$/
      : new RegExp(`^(\\d+)(?:\\.(\\d{1,${String(digitsAfterPoint)}}))?$`);
  const match = pattern.exec(text.trim());
  const units = match?.[1];

  if (units === undefined) {
    return null;
  }

  return `${units}${(match?.[2] ?? '').padEnd(digitsAfterPoint, ZERO)}`.replace(LEADING_ZEROS, '');
}

/**
 * The amount as a number of major units, only for plotting a chart: numbers lose digits
 * beyond the safe integer range, so tables and tooltips show formatMoney's exact text.
 */
export function toChartValue(money: Money): number {
  return Number(toDecimalString(money));
}
