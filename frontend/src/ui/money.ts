import type { components } from '../api/schema';

type Money = components['schemas']['Money'];

const DEFAULT_LOCALE = 'en-US';
const MINUS = '-';
const ZERO = '0';
const DECIMAL_POINT = '.';

/**
 * Formats an API amount in minor units. The amount is turned into a decimal string and
 * formatted as a string, so even amounts beyond the safe integer range keep every digit.
 */
export function formatMoney(money: Money, locale: string = DEFAULT_LOCALE): string {
  const format = new Intl.NumberFormat(locale, { style: 'currency', currency: money.currency });
  const fractionDigits = format.resolvedOptions().maximumFractionDigits ?? 0;
  const negative = money.amount.startsWith(MINUS);
  const digits = (negative ? money.amount.slice(MINUS.length) : money.amount).padStart(
    fractionDigits + 1,
    ZERO,
  );
  const units = digits.slice(0, digits.length - fractionDigits);
  const fraction = digits.slice(digits.length - fractionDigits);
  const decimal = fractionDigits === 0 ? units : `${units}${DECIMAL_POINT}${fraction}`;

  return format.format(`${negative ? MINUS : ''}${decimal}` as Intl.StringNumericLiteral);
}
