import test from 'node:test';
import assert from 'node:assert/strict';
import { redactSecrets } from '../src/redact.mjs';

test('redactSecrets masks bearer tokens and assertions', () => {
  const input = {
    access_token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.secret.payload',
    assertion: 'merchant-assertion-token',
    nested: {
      Authorization: 'Bearer abc.def.ghi',
    },
  };

  assert.deepEqual(redactSecrets(input), {
    access_token: '[REDACTED]',
    assertion: '[REDACTED]',
    nested: {
      Authorization: 'Bearer [REDACTED]',
    },
  });
});

test('redactSecrets masks env-style keys and lowercase authorization headers', () => {
  const input = {
    ZETTLE_ASSERTION: 'merchant-assertion-token',
    ZETTLE_PAIRING_CODE: 'ERFS2342',
    nested: {
      authorization: 'Bearer abc.def.ghi',
    },
  };

  assert.deepEqual(redactSecrets(input), {
    ZETTLE_ASSERTION: '[REDACTED]',
    ZETTLE_PAIRING_CODE: 'ER******',
    nested: {
      authorization: 'Bearer [REDACTED]',
    },
  });
});

test('redactSecrets masks pairing codes while keeping shape readable', () => {
  const input = {
    code: 'ERFS2342',
  };

  assert.deepEqual(redactSecrets(input), {
    code: 'ER******',
  });
});
