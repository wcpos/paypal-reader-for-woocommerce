import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { writeMarkdown, writeRedactedJson } from '../src/evidence.mjs';

test('writeRedactedJson keeps secrets redacted and writeMarkdown normalizes trailing newlines', async () => {
  const dir = await mkdtemp(join(tmpdir(), 'paypal-reader-spike-'));
  const jsonPath = join(dir, 'evidence.json');
  const mdPath = join(dir, 'notes.md');

  await writeRedactedJson(jsonPath, {
    ZETTLE_ASSERTION: 'merchant-assertion-token',
    nested: {
      ZETTLE_PAIRING_CODE: 'ERFS2342',
      authorization: 'Bearer abc.def.ghi',
    },
  });

  await writeMarkdown(mdPath, 'line one');

  assert.equal(
    await readFile(jsonPath, 'utf8'),
    [
      '{',
      '  "ZETTLE_ASSERTION": "[REDACTED]",',
      '  "nested": {',
      '    "ZETTLE_PAIRING_CODE": "ER******",',
      '    "authorization": "Bearer [REDACTED]"',
      '  }',
      '}',
      '',
    ].join('\n')
  );
  assert.equal(await readFile(mdPath, 'utf8'), 'line one\n');
});
