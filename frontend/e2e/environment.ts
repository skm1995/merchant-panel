/** Reads a variable the end-to-end tests cannot run without, from docker/env/*.env. */
export function requiredVariable(name: string): string {
  const value = process.env[name];

  if (value === undefined || value.length === 0) {
    throw new Error(`Required environment variable "${name}" is not set.`);
  }

  return value;
}
