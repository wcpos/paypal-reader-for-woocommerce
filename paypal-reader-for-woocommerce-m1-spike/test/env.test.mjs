import test from 'node:test';
import assert from 'node:assert/strict';
import { readRequiredEnv } from '../src/env.mjs';

test('readRequiredEnv returns requested values', () => {
  const env = readRequiredEnv(['ZETTLE_CLIENT_ID'], {
    ZETTLE_CLIENT_ID: 'client-123',
  });

  assert.deepEqual(env, {
    ZETTLE_CLIENT_ID: 'client-123',
  });
});

test('readRequiredEnv throws when a value is missing', () => {
  assert.throws(
    () => readRequiredEnv(['ZETTLE_CLIENT_ID', 'ZETTLE_ASSERTION'], { ZETTLE_CLIENT_ID: 'client-123' }),
    /Missing required environment variables: ZETTLE_ASSERTION/
  );
});
