/**
 * Joins the class names that apply, skipping CSS module classes that are not defined.
 */
export function classNames(...names: (string | undefined | false)[]): string {
  return names
    .filter((name): name is string => typeof name === 'string' && name.length > 0)
    .join(' ');
}
