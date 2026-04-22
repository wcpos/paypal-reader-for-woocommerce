export function readRequiredEnv(names, source = process.env) {
  const values = {};
  const missing = [];

  for (const name of names) {
    const value = source[name];

    if (!value) {
      missing.push(name);
      continue;
    }

    values[name] = value;
  }

  if (missing.length > 0) {
    throw new Error(`Missing required environment variables: ${missing.join(', ')}`);
  }

  return values;
}
