import { mkdir, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';
import { redactSecrets } from './redact.mjs';

export async function writeRedactedJson(path, value) {
  await mkdir(dirname(path), { recursive: true });
  await writeFile(path, `${JSON.stringify(redactSecrets(value), null, 2)}\n`);
}

export async function writeMarkdown(path, contents) {
  await mkdir(dirname(path), { recursive: true });
  await writeFile(path, contents.endsWith('\n') ? contents : `${contents}\n`);
}
