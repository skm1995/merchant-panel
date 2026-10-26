import type { components } from './schema';

type Problem = components['schemas']['Problem'];

const UNEXPECTED_ERROR = 'Something went wrong. Please try again.';

function isProblem(error: unknown): error is Problem {
  return (
    typeof error === 'object' &&
    error !== null &&
    'detail' in error &&
    typeof error.detail === 'string'
  );
}

/**
 * The message to show for a failed API call: the problem detail, or a generic message when
 * the response carried none.
 */
export function problemMessage(error: unknown): string {
  return isProblem(error) ? error.detail : UNEXPECTED_ERROR;
}
