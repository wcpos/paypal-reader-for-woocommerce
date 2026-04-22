function redactString(value, key = '') {
  const normalizedKey = key.toLowerCase();

  if ((normalizedKey === 'code' || normalizedKey.includes('pairing_code')) && value.length === 8) {
    return `${value.slice(0, 2)}******`;
  }

  if (normalizedKey === 'authorization' && /^Bearer\s+/i.test(value)) {
    return 'Bearer [REDACTED]';
  }

  if (normalizedKey.includes('token') || normalizedKey.includes('assertion')) {
    return '[REDACTED]';
  }

  return value;
}

export function redactSecrets(value, key = '') {
  if (Array.isArray(value)) {
    return value.map((entry) => redactSecrets(entry, key));
  }

  if (value && typeof value === 'object') {
    return Object.fromEntries(
      Object.entries(value).map(([entryKey, entryValue]) => [entryKey, redactSecrets(entryValue, entryKey)])
    );
  }

  if (typeof value === 'string') {
    return redactString(value, key);
  }

  return value;
}
