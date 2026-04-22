# PayPal Reader for WooCommerce M1 Discovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce a validated M1 evidence pack that proves or disproves the self-hosted PayPal Reader / Zettle Reader Connect path and leaves the project ready for a follow-up M2 production implementation plan.

**Architecture:** Build a disposable spike workspace outside the future production plugin repo. Use small Node CLI wrappers for OAuth and Reader Connect integrator REST calls, a browser-based WebSocket harness for live reader messaging, and redacted evidence documents that get folded back into the design spec. Do not scaffold the production plugin repo until the final go/no-go record says M1 passed.

**Tech Stack:** Markdown, Node.js ESM with the built-in test runner, browser-native WebSocket, Zettle OAuth + Reader Connect integrator APIs, real PayPal Reader hardware.

---

## File Structure

**Disposable spike workspace**
- Create: `paypal-reader-for-woocommerce-m1-spike/package.json` — local scripts for tests and live commands.
- Create: `paypal-reader-for-woocommerce-m1-spike/.env.example` — exact env variable names required for the spike.
- Create: `paypal-reader-for-woocommerce-m1-spike/README.md` — operator instructions for running the spike.
- Create: `paypal-reader-for-woocommerce-m1-spike/src/env.mjs` — required-env loader.
- Create: `paypal-reader-for-woocommerce-m1-spike/src/redact.mjs` — token/assertion/pairing-code redaction helpers.
- Create: `paypal-reader-for-woocommerce-m1-spike/src/evidence.mjs` — redacted file writers.
- Create: `paypal-reader-for-woocommerce-m1-spike/src/zettle-oauth.mjs` — assertion-grant token helper.
- Create: `paypal-reader-for-woocommerce-m1-spike/src/reader-connect-rest.mjs` — claim/list/delete/configure-session REST wrappers.
- Create: `paypal-reader-for-woocommerce-m1-spike/src/ws-messages.mjs` — WebSocket envelope builders.
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/get-token.mjs` — obtain access token and write redacted evidence.
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/list-links.mjs` — list current reader links.
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/claim-link.mjs` — claim an 8-character link code.
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/delete-link.mjs` — remove a link by `linkId`.
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/configure-session.mjs` — request WebSocket `location` + `authorized` map.
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/write-evidence-pack.mjs` — summarize the spike outputs into markdown/json files.
- Create: `paypal-reader-for-woocommerce-m1-spike/tools/ws-harness/index.html` — browser UI for connect/status/payment/cancel.
- Create: `paypal-reader-for-woocommerce-m1-spike/tools/ws-harness/app.js` — browser WebSocket logic.
- Create: `paypal-reader-for-woocommerce-m1-spike/test/env.test.mjs` — env-loader tests.
- Create: `paypal-reader-for-woocommerce-m1-spike/test/redact.test.mjs` — redaction tests.
- Create: `paypal-reader-for-woocommerce-m1-spike/test/api-client.test.mjs` — OAuth + REST request-shape tests.
- Create: `paypal-reader-for-woocommerce-m1-spike/test/ws-messages.test.mjs` — STATUS/PAYMENT/CANCEL message-shape tests.

**Evidence outputs**
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/auth-decision.md` — self-hosted vs partner-hosted decision.
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/scopes.md` — tested scope list and justification.
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/session-sample.json` — redacted configure-session sample.
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/payment-run-001.md` — one successful payment transcript.
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/reconciliation-browser-drop.md` — disconnect scenario write-up.
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/go-no-go.md` — final M1 pass/fail summary.

**Spec handoff**
- Modify: `docs/superpowers/specs/2026-04-22-paypal-reader-for-woocommerce-design.md` — replace provisional language with M1 findings and freeze the M2 starting point.

---

### Task 1: Bootstrap the disposable spike workspace

**Files:**
- Create: `paypal-reader-for-woocommerce-m1-spike/package.json`
- Create: `paypal-reader-for-woocommerce-m1-spike/.env.example`
- Create: `paypal-reader-for-woocommerce-m1-spike/README.md`
- Create: `paypal-reader-for-woocommerce-m1-spike/src/env.mjs`
- Create: `paypal-reader-for-woocommerce-m1-spike/src/redact.mjs`
- Create: `paypal-reader-for-woocommerce-m1-spike/src/evidence.mjs`
- Test: `paypal-reader-for-woocommerce-m1-spike/test/env.test.mjs`
- Test: `paypal-reader-for-woocommerce-m1-spike/test/redact.test.mjs`

- [ ] **Step 1: Write the failing tests for env loading and redaction**

```js
// paypal-reader-for-woocommerce-m1-spike/test/env.test.mjs
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
```

```js
// paypal-reader-for-woocommerce-m1-spike/test/redact.test.mjs
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

test('redactSecrets masks pairing codes while keeping shape readable', () => {
  const input = {
    code: 'ERFS2342',
  };

  assert.deepEqual(redactSecrets(input), {
    code: 'ER******',
  });
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd /Users/kilbot/Projects/paypal-reader-for-woocommerce-m1-spike && node --test`

Expected: FAIL with module-not-found errors for `../src/env.mjs` and `../src/redact.mjs`.

- [ ] **Step 3: Write the minimal workspace files and helpers**

```json
// paypal-reader-for-woocommerce-m1-spike/package.json
{
  "name": "paypal-reader-for-woocommerce-m1-spike",
  "private": true,
  "type": "module",
  "scripts": {
    "test": "node --test",
    "get-token": "node scripts/get-token.mjs",
    "list-links": "node scripts/list-links.mjs",
    "claim-link": "node scripts/claim-link.mjs",
    "delete-link": "node scripts/delete-link.mjs",
    "configure-session": "node scripts/configure-session.mjs",
    "write-evidence-pack": "node scripts/write-evidence-pack.mjs"
  }
}
```

```dotenv
# paypal-reader-for-woocommerce-m1-spike/.env.example
ZETTLE_CLIENT_ID=
ZETTLE_ASSERTION=
ZETTLE_PAIRING_CODE=
ZETTLE_LINK_ID=
ZETTLE_DEVICE_NAME='WCPOS PayPal Reader Spike'
ZETTLE_CHANNEL_ID=wcpos-m1
ZETTLE_AMOUNT_MINOR=100
ZETTLE_CURRENCY=USD
```

```md
# paypal-reader-for-woocommerce-m1-spike/README.md
# PayPal Reader for WooCommerce M1 Spike

This directory is disposable. It exists only to prove the M1 gates from the design spec.

## Rules
- Do not commit real credentials.
- Copy `.env.example` to `.env` and fill in merchant-specific values locally.
- Write all durable findings into `evidence/m1/`.
- Do not start the production plugin repo from this workspace.

## Commands
- `npm test`
- `npm run get-token`
- `npm run list-links`
- `npm run claim-link`
- `npm run configure-session`
- `npm run write-evidence-pack`
```

```js
// paypal-reader-for-woocommerce-m1-spike/src/env.mjs
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
```

```js
// paypal-reader-for-woocommerce-m1-spike/src/redact.mjs
function redactString(value, key = '') {
  if (key === 'code' && value.length === 8) {
    return `${value.slice(0, 2)}******`;
  }

  if (key === 'Authorization' && value.startsWith('Bearer ')) {
    return 'Bearer [REDACTED]';
  }

  if (key.includes('token') || key.includes('assertion')) {
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
```

```js
// paypal-reader-for-woocommerce-m1-spike/src/evidence.mjs
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
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd /Users/kilbot/Projects/paypal-reader-for-woocommerce-m1-spike && npm test`

Expected: PASS with 4 passing tests.

- [ ] **Step 5: Commit**

```bash
git add paypal-reader-for-woocommerce-m1-spike/package.json \
  paypal-reader-for-woocommerce-m1-spike/.env.example \
  paypal-reader-for-woocommerce-m1-spike/README.md \
  paypal-reader-for-woocommerce-m1-spike/src/env.mjs \
  paypal-reader-for-woocommerce-m1-spike/src/redact.mjs \
  paypal-reader-for-woocommerce-m1-spike/src/evidence.mjs \
  paypal-reader-for-woocommerce-m1-spike/test/env.test.mjs \
  paypal-reader-for-woocommerce-m1-spike/test/redact.test.mjs

git commit -m "chore: bootstrap paypal reader M1 spike workspace"
```

---

### Task 2: Add assertion-grant OAuth and Reader Connect REST clients

**Files:**
- Create: `paypal-reader-for-woocommerce-m1-spike/src/zettle-oauth.mjs`
- Create: `paypal-reader-for-woocommerce-m1-spike/src/reader-connect-rest.mjs`
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/get-token.mjs`
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/list-links.mjs`
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/claim-link.mjs`
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/delete-link.mjs`
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/configure-session.mjs`
- Test: `paypal-reader-for-woocommerce-m1-spike/test/api-client.test.mjs`

- [ ] **Step 1: Write the failing request-shape tests**

```js
// paypal-reader-for-woocommerce-m1-spike/test/api-client.test.mjs
import test from 'node:test';
import assert from 'node:assert/strict';
import { buildAssertionGrantBody } from '../src/zettle-oauth.mjs';
import { buildConfigureSessionRequest } from '../src/reader-connect-rest.mjs';

test('buildAssertionGrantBody formats the oauth assertion grant payload', () => {
  const body = buildAssertionGrantBody({
    clientId: 'client-123',
    assertion: 'assertion-456',
  });

  assert.equal(
    body.toString(),
    'grant_type=urn%3Aietf%3Aparams%3Aoauth%3Agrant-type%3Ajwt-bearer&client_id=client-123&assertion=assertion-456'
  );
});

test('buildConfigureSessionRequest maps one link to one channel', () => {
  assert.deepEqual(
    buildConfigureSessionRequest({ linkId: 'link-123', channelId: 'wcpos-m1' }),
    {
      links: {
        'link-123': ['wcpos-m1'],
      },
    }
  );
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd /Users/kilbot/Projects/paypal-reader-for-woocommerce-m1-spike && npm test`

Expected: FAIL with module-not-found errors for `zettle-oauth.mjs` and `reader-connect-rest.mjs`.

- [ ] **Step 3: Implement the OAuth + REST helpers and CLI scripts**

```js
// paypal-reader-for-woocommerce-m1-spike/src/zettle-oauth.mjs
import { readRequiredEnv } from './env.mjs';

export function buildAssertionGrantBody({ clientId, assertion }) {
  return new URLSearchParams({
    grant_type: 'urn:ietf:params:oauth:grant-type:jwt-bearer',
    client_id: clientId,
    assertion,
  });
}

export async function fetchAccessToken(fetchImpl = fetch, source = process.env) {
  const { ZETTLE_CLIENT_ID, ZETTLE_ASSERTION } = readRequiredEnv(['ZETTLE_CLIENT_ID', 'ZETTLE_ASSERTION'], source);
  const response = await fetchImpl('https://oauth.zettle.com/token', {
    method: 'POST',
    headers: {
      'content-type': 'application/x-www-form-urlencoded',
    },
    body: buildAssertionGrantBody({ clientId: ZETTLE_CLIENT_ID, assertion: ZETTLE_ASSERTION }),
  });

  if (!response.ok) {
    throw new Error(`Token request failed with ${response.status}`);
  }

  return response.json();
}
```

```js
// paypal-reader-for-woocommerce-m1-spike/src/reader-connect-rest.mjs
const BASE_URL = 'https://reader-connect.zettle.com/v1/integrator';

export function buildConfigureSessionRequest({ linkId, channelId }) {
  return {
    links: {
      [linkId]: [channelId],
    },
  };
}

export async function readerConnectFetch(path, { token, method = 'GET', body } = {}, fetchImpl = fetch) {
  const response = await fetchImpl(`${BASE_URL}${path}`, {
    method,
    headers: {
      authorization: `Bearer ${token}`,
      'content-type': 'application/json',
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  if (!response.ok) {
    throw new Error(`Reader Connect request failed with ${response.status}`);
  }

  return response.json();
}

export function claimLinkOffer({ token, code, deviceName }, fetchImpl = fetch) {
  return readerConnectFetch(
    '/link-offers/claim',
    {
      method: 'POST',
      token,
      body: {
        code,
        tags: {
          deviceName,
        },
      },
    },
    fetchImpl
  );
}

export function getLinks({ token }, fetchImpl = fetch) {
  return readerConnectFetch('/links', { token }, fetchImpl);
}

export function deleteLink({ token, linkId }, fetchImpl = fetch) {
  return readerConnectFetch(`/links/${linkId}`, { method: 'DELETE', token }, fetchImpl);
}

export function configureSession({ token, linkId, channelId }, fetchImpl = fetch) {
  return readerConnectFetch(
    '/sessions',
    {
      method: 'POST',
      token,
      body: buildConfigureSessionRequest({ linkId, channelId }),
    },
    fetchImpl
  );
}
```

```js
// paypal-reader-for-woocommerce-m1-spike/scripts/get-token.mjs
import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import { writeRedactedJson } from '../src/evidence.mjs';

const result = await fetchAccessToken();
await writeRedactedJson('evidence/m1/token-response.json', result);
console.log(JSON.stringify({ expires_in: result.expires_in }, null, 2));
```

```js
// paypal-reader-for-woocommerce-m1-spike/scripts/claim-link.mjs
import { readRequiredEnv } from '../src/env.mjs';
import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import { claimLinkOffer } from '../src/reader-connect-rest.mjs';
import { writeRedactedJson } from '../src/evidence.mjs';

const { ZETTLE_PAIRING_CODE, ZETTLE_DEVICE_NAME } = readRequiredEnv(['ZETTLE_PAIRING_CODE', 'ZETTLE_DEVICE_NAME']);
const token = await fetchAccessToken();
const link = await claimLinkOffer({
  token: token.access_token,
  code: ZETTLE_PAIRING_CODE,
  deviceName: ZETTLE_DEVICE_NAME,
});

await writeRedactedJson('evidence/m1/claim-link-response.json', link);
console.log(JSON.stringify({ linkId: link.id }, null, 2));
```

```js
// paypal-reader-for-woocommerce-m1-spike/scripts/list-links.mjs
import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import { getLinks } from '../src/reader-connect-rest.mjs';
import { writeRedactedJson } from '../src/evidence.mjs';

const token = await fetchAccessToken();
const links = await getLinks({ token: token.access_token });

await writeRedactedJson('evidence/m1/links-list.json', links);
console.log(JSON.stringify(links, null, 2));
```

```js
// paypal-reader-for-woocommerce-m1-spike/scripts/delete-link.mjs
import { readRequiredEnv } from '../src/env.mjs';
import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import { deleteLink } from '../src/reader-connect-rest.mjs';

const { ZETTLE_LINK_ID } = readRequiredEnv(['ZETTLE_LINK_ID']);
const token = await fetchAccessToken();
await deleteLink({ token: token.access_token, linkId: ZETTLE_LINK_ID });

console.log(JSON.stringify({ deleted: ZETTLE_LINK_ID }, null, 2));
```

```js
// paypal-reader-for-woocommerce-m1-spike/scripts/configure-session.mjs
import { readRequiredEnv } from '../src/env.mjs';
import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import { configureSession } from '../src/reader-connect-rest.mjs';
import { writeRedactedJson } from '../src/evidence.mjs';

const { ZETTLE_LINK_ID, ZETTLE_CHANNEL_ID } = readRequiredEnv(['ZETTLE_LINK_ID', 'ZETTLE_CHANNEL_ID']);
const token = await fetchAccessToken();
const session = await configureSession({
  token: token.access_token,
  linkId: ZETTLE_LINK_ID,
  channelId: ZETTLE_CHANNEL_ID,
});

await writeRedactedJson('evidence/m1/session-sample.json', session);
console.log(JSON.stringify(session, null, 2));
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd /Users/kilbot/Projects/paypal-reader-for-woocommerce-m1-spike && npm test`

Expected: PASS with the new API-client tests included.

- [ ] **Step 5: Run the live auth + REST checks and capture evidence**

Run:

```bash
cd /Users/kilbot/Projects/paypal-reader-for-woocommerce-m1-spike
cp .env.example .env
set -a
source .env
set +a
npm run get-token
npm run list-links
npm run claim-link
npm run configure-session
```

Expected:
- `npm run get-token` prints an `expires_in` value and writes `evidence/m1/token-response.json`
- `npm run claim-link` prints a `linkId`
- `npm run configure-session` prints a JSON object containing `location`, `authorized`, and `failed`

- [ ] **Step 6: Commit**

```bash
git add paypal-reader-for-woocommerce-m1-spike/src/zettle-oauth.mjs \
  paypal-reader-for-woocommerce-m1-spike/src/reader-connect-rest.mjs \
  paypal-reader-for-woocommerce-m1-spike/scripts/get-token.mjs \
  paypal-reader-for-woocommerce-m1-spike/scripts/list-links.mjs \
  paypal-reader-for-woocommerce-m1-spike/scripts/claim-link.mjs \
  paypal-reader-for-woocommerce-m1-spike/scripts/delete-link.mjs \
  paypal-reader-for-woocommerce-m1-spike/scripts/configure-session.mjs \
  paypal-reader-for-woocommerce-m1-spike/test/api-client.test.mjs \
  paypal-reader-for-woocommerce-m1-spike/evidence/m1/token-response.json \
  paypal-reader-for-woocommerce-m1-spike/evidence/m1/claim-link-response.json \
  paypal-reader-for-woocommerce-m1-spike/evidence/m1/session-sample.json

git commit -m "feat: add oauth and reader connect rest spike clients"
```

---

### Task 3: Build the browser WebSocket harness and prove reader status flow

**Files:**
- Create: `paypal-reader-for-woocommerce-m1-spike/src/ws-messages.mjs`
- Create: `paypal-reader-for-woocommerce-m1-spike/tools/ws-harness/index.html`
- Create: `paypal-reader-for-woocommerce-m1-spike/tools/ws-harness/app.js`
- Test: `paypal-reader-for-woocommerce-m1-spike/test/ws-messages.test.mjs`
- Modify: `paypal-reader-for-woocommerce-m1-spike/README.md`

- [ ] **Step 1: Write the failing WebSocket message-shape tests**

```js
// paypal-reader-for-woocommerce-m1-spike/test/ws-messages.test.mjs
import test from 'node:test';
import assert from 'node:assert/strict';
import { buildStatusRequestMessage, buildPaymentRequestMessage, buildCancelPaymentMessage } from '../src/ws-messages.mjs';

test('buildStatusRequestMessage creates a STATUS_REQUEST envelope', () => {
  assert.deepEqual(
    buildStatusRequestMessage({ linkId: 'link-123', channelId: 'wcpos-m1', messageId: 'msg-1' }),
    {
      type: 'MESSAGE',
      linkId: 'link-123',
      channelId: 'wcpos-m1',
      messageId: 'msg-1',
      payload: {
        type: 'STATUS_REQUEST',
      },
    }
  );
});

test('buildPaymentRequestMessage uses the server-owned internalTraceId', () => {
  assert.deepEqual(
    buildPaymentRequestMessage({
      linkId: 'link-123',
      channelId: 'wcpos-m1',
      messageId: 'msg-2',
      accessToken: 'token-123',
      expiresAt: 1700000000000,
      internalTraceId: 'attempt-123',
      amount: 100,
    }).payload,
    {
      type: 'PAYMENT_REQUEST',
      accessToken: 'token-123',
      expiresAt: 1700000000000,
      internalTraceId: 'attempt-123',
      amount: 100,
      tippingType: 'NONE',
    }
  );
});

test('buildCancelPaymentMessage creates a cancel envelope', () => {
  assert.equal(
    buildCancelPaymentMessage({ linkId: 'link-123', channelId: 'wcpos-m1', messageId: 'msg-3' }).payload.type,
    'CANCEL_PAYMENT_REQUEST'
  );
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd /Users/kilbot/Projects/paypal-reader-for-woocommerce-m1-spike && npm test`

Expected: FAIL with module-not-found errors for `../src/ws-messages.mjs`.

- [ ] **Step 3: Implement the message builders and browser harness**

```js
// paypal-reader-for-woocommerce-m1-spike/src/ws-messages.mjs
export function buildStatusRequestMessage({ linkId, channelId, messageId }) {
  return {
    type: 'MESSAGE',
    linkId,
    channelId,
    messageId,
    payload: {
      type: 'STATUS_REQUEST',
    },
  };
}

export function buildPaymentRequestMessage({
  linkId,
  channelId,
  messageId,
  accessToken,
  expiresAt,
  internalTraceId,
  amount,
}) {
  return {
    type: 'MESSAGE',
    linkId,
    channelId,
    messageId,
    payload: {
      type: 'PAYMENT_REQUEST',
      accessToken,
      expiresAt,
      internalTraceId,
      amount,
      tippingType: 'NONE',
    },
  };
}

export function buildCancelPaymentMessage({ linkId, channelId, messageId }) {
  return {
    type: 'MESSAGE',
    linkId,
    channelId,
    messageId,
    payload: {
      type: 'CANCEL_PAYMENT_REQUEST',
    },
  };
}
```

```html
<!-- paypal-reader-for-woocommerce-m1-spike/tools/ws-harness/index.html -->
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>PayPal Reader M1 WebSocket Harness</title>
  </head>
  <body>
    <h1>PayPal Reader M1 WebSocket Harness</h1>
    <form id="controls">
      <input id="location" placeholder="WebSocket location" size="80" />
      <input id="linkId" placeholder="linkId" />
      <input id="channelId" placeholder="channelId" />
      <input id="accessToken" placeholder="short-lived access token" size="60" />
      <input id="expiresAt" placeholder="expiresAt" />
      <input id="attemptId" placeholder="internalTraceId / attemptId" />
      <input id="amount" placeholder="amount in minor units" />
      <button type="button" id="connect">Connect</button>
      <button type="button" id="status">Send status</button>
      <button type="button" id="payment">Send payment</button>
      <button type="button" id="cancel">Send cancel</button>
    </form>
    <pre id="log"></pre>
    <script type="module" src="./app.js"></script>
  </body>
</html>
```

```js
// paypal-reader-for-woocommerce-m1-spike/tools/ws-harness/app.js
import {
  buildStatusRequestMessage,
  buildPaymentRequestMessage,
  buildCancelPaymentMessage,
} from '../../src/ws-messages.mjs';

const logNode = document.querySelector('#log');
let socket;

function field(id) {
  return document.querySelector(`#${id}`).value;
}

function log(label, payload) {
  logNode.textContent += `${label}\n${JSON.stringify(payload, null, 2)}\n\n`;
}

function messageId() {
  return crypto.randomUUID();
}

document.querySelector('#connect').addEventListener('click', () => {
  socket = new WebSocket(field('location'));
  socket.addEventListener('open', () => log('OPEN', { location: field('location') }));
  socket.addEventListener('message', (event) => log('MESSAGE', JSON.parse(event.data)));
  socket.addEventListener('close', () => log('CLOSE', {}));
  socket.addEventListener('error', () => log('ERROR', {}));
});

document.querySelector('#status').addEventListener('click', () => {
  const payload = buildStatusRequestMessage({
    linkId: field('linkId'),
    channelId: field('channelId'),
    messageId: messageId(),
  });
  socket.send(JSON.stringify(payload));
  log('SENT STATUS', payload);
});

document.querySelector('#payment').addEventListener('click', () => {
  const payload = buildPaymentRequestMessage({
    linkId: field('linkId'),
    channelId: field('channelId'),
    messageId: messageId(),
    accessToken: field('accessToken'),
    expiresAt: Number(field('expiresAt')),
    internalTraceId: field('attemptId'),
    amount: Number(field('amount')),
  });
  socket.send(JSON.stringify(payload));
  log('SENT PAYMENT', payload);
});

document.querySelector('#cancel').addEventListener('click', () => {
  const payload = buildCancelPaymentMessage({
    linkId: field('linkId'),
    channelId: field('channelId'),
    messageId: messageId(),
  });
  socket.send(JSON.stringify(payload));
  log('SENT CANCEL', payload);
});
```

```md
<!-- README section to append -->
## Browser WebSocket harness

Run a local static server from the spike root:
- `python3 -m http.server 4173`

Then open `http://localhost:4173/tools/ws-harness/` and paste the `location`, `linkId`, `channelId`, token, expiry, attempt ID, and amount from the live spike commands.
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd /Users/kilbot/Projects/paypal-reader-for-woocommerce-m1-spike && npm test`

Expected: PASS with the new WebSocket message tests included.

- [ ] **Step 5: Prove the status flow on real hardware**

Run:

```bash
cd /Users/kilbot/Projects/paypal-reader-for-woocommerce-m1-spike
python3 -m http.server 4173
```

Then in the browser harness:
1. paste the `location` from `npm run configure-session`
2. paste the selected `linkId`
3. paste `wcpos-m1` as `channelId`
4. click **Connect**
5. click **Send status**

Expected:
- the log shows `OPEN`
- the log shows a `STATUS_RESPONSE`
- the status value is one of `READY`, `BUSY`, or `NOT_CONNECTED`

- [ ] **Step 6: Commit**

```bash
git add paypal-reader-for-woocommerce-m1-spike/src/ws-messages.mjs \
  paypal-reader-for-woocommerce-m1-spike/tools/ws-harness/index.html \
  paypal-reader-for-woocommerce-m1-spike/tools/ws-harness/app.js \
  paypal-reader-for-woocommerce-m1-spike/test/ws-messages.test.mjs \
  paypal-reader-for-woocommerce-m1-spike/README.md

git commit -m "feat: add websocket harness for reader status validation"
```

---

### Task 4: Prove payment, cancellation, and reconciliation evidence

**Files:**
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/payment-run-001.md`
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/reconciliation-browser-drop.md`
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/scopes.md`
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/auth-decision.md`
- Create: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/go-no-go.md`
- Create: `paypal-reader-for-woocommerce-m1-spike/scripts/write-evidence-pack.mjs`

- [ ] **Step 1: Write the markdown evidence templates and pack writer**

```js
// paypal-reader-for-woocommerce-m1-spike/scripts/write-evidence-pack.mjs
import { writeMarkdown } from '../src/evidence.mjs';

await writeMarkdown(
  'evidence/m1/scopes.md',
  `# Scopes\n\n- READ:USERINFO — linking and link listing\n- WRITE:USERINFO — claim-link and session setup\n- READ:PAYMENT — payment flow validation\n- WRITE:PAYMENT — payment request execution\n`
);

await writeMarkdown(
  'evidence/m1/auth-decision.md',
  `# Auth Decision\n\n## Decision\nUse the self-hosted assertion-grant model if and only if the token obtained in this spike succeeds for link claim, session configuration, and payment execution.\n\n## Rejection rule\nIf any one of those operations requires a different app model, M1 fails and M2 must not start.\n`
);

await writeMarkdown(
  'evidence/m1/go-no-go.md',
  `# Go / No-Go\n\n- [ ] Access token obtained with assertion grant\n- [ ] Reader linked successfully\n- [ ] Session configured successfully\n- [ ] STATUS_REQUEST succeeded\n- [ ] PAYMENT_REQUEST succeeded\n- [ ] Server-side verification mechanism named and tested\n- [ ] Reconciliation rule written and tested\n\nIf any checkbox remains unchecked, record NO-GO and stop before M2.\n`
);
```

```md
<!-- paypal-reader-for-woocommerce-m1-spike/evidence/m1/payment-run-001.md -->
# Payment Run 001

## Inputs
- Attempt ID / internal trace ID: record the exact redacted attempt ID used in this run.
- Reader linkId: copy the redacted `linkId` that was used for this run.
- Amount minor units: record the exact amount used for this run.

## Reader Connect progress observed
- `STARTING_TRANSACTION`
- `PREPARING`
- `PRESENT_CARD`
- `CARD_PRESENTED`
- `APPROVED`
- `COMPLETED`

## Result references observed
- `CARD_PAYMENT_UUID`: record the redacted UUID if present.
- `trackingId`: record the redacted value or write `null`.
- `checkoutUUID`: record the redacted value or write `null`.

## Verification
- Endpoint/mechanism tested: name the exact server-side verification mechanism that was exercised.
- Outcome: write `verified success`, `verified failure`, or `verification unresolved`.

## Decision
- The browser result was treated as: `verification input only`
- The authoritative server-side proof was: name the exact source of truth that justified the decision.
```

```md
<!-- paypal-reader-for-woocommerce-m1-spike/evidence/m1/reconciliation-browser-drop.md -->
# Reconciliation: Browser Drop Mid-Payment

## Scenario
The browser tab closes after `payment_requested` and before the final result is visible to the operator.

## Required system behavior
1. Mark the attempt `reconciliation_required`.
2. Block any new attempt for the same order.
3. Run the server-side verification lookup using the known payment references.
4. Re-open checkout only if the attempt is conclusively unpaid.

## Manual test notes
- Time of disconnect:
- Last progress event seen:
- Server-side verification result:
- Retry allowed after reconciliation:
```

- [ ] **Step 2: Replace the inline markers with real findings from the hardware run**

Update `payment-run-001.md` and `reconciliation-browser-drop.md` so every instruction line contains the actual redacted values from the live reader session.

- [ ] **Step 3: Run the live payment and cancellation checks**

Use the browser harness from Task 3 with a fresh attempt ID and a real amount.

Expected live checks:
- a full successful payment produces `PAYMENT_PROGRESS_RESPONSE` updates and a final `PAYMENT_RESULT_RESPONSE`
- the final result includes at least one stable reference such as `CARD_PAYMENT_UUID`
- a manual cancel attempt produces either a successful cancellation signal or a clearly ambiguous state that gets recorded as reconciliation-required

- [ ] **Step 4: Write the evidence pack files**

Run: `cd /Users/kilbot/Projects/paypal-reader-for-woocommerce-m1-spike && npm run write-evidence-pack`

Expected:
- `evidence/m1/scopes.md` exists
- `evidence/m1/auth-decision.md` exists
- `evidence/m1/go-no-go.md` exists

- [ ] **Step 5: Commit**

```bash
git add paypal-reader-for-woocommerce-m1-spike/scripts/write-evidence-pack.mjs \
  paypal-reader-for-woocommerce-m1-spike/evidence/m1/payment-run-001.md \
  paypal-reader-for-woocommerce-m1-spike/evidence/m1/reconciliation-browser-drop.md \
  paypal-reader-for-woocommerce-m1-spike/evidence/m1/scopes.md \
  paypal-reader-for-woocommerce-m1-spike/evidence/m1/auth-decision.md \
  paypal-reader-for-woocommerce-m1-spike/evidence/m1/go-no-go.md

git commit -m "docs: capture M1 payment and reconciliation evidence"
```

---

### Task 5: Fold M1 findings back into the design spec and stop at the gate

**Files:**
- Modify: `docs/superpowers/specs/2026-04-22-paypal-reader-for-woocommerce-design.md`
- Modify: `paypal-reader-for-woocommerce-m1-spike/evidence/m1/go-no-go.md`

- [ ] **Step 1: Replace the provisional M1 sections in the design spec with actual findings**

Update the following sections in `docs/superpowers/specs/2026-04-22-paypal-reader-for-woocommerce-design.md`:
- `6. External dependencies and references`
- `7. M1 discovery spike`
- `8.1 Auth model`
- `8.2 Verification source of truth`
- `8.3 Browser disconnect and reconciliation`
- `8.4 Idempotency and retries`
- `8.5 Refund scope`
- `13.3 Currency and amount representation`
- `13.4 Tipping scope`
- `24. Release criteria`
- `25. Recommendation`

Use this exact shape when replacing the open-decision bullets:

```md
### Final M1 decision
- Auth model: `self-hosted assertion grant` OR `partner-hosted required`
- Required scopes: `READ:USERINFO`, `WRITE:USERINFO`, `READ:PAYMENT`, `WRITE:PAYMENT` plus any additional verified scopes
- Verification source of truth: `ACTUAL_ENDPOINT_OR_MECHANISM`
- Retry rule after ambiguity: `BLOCK_UNTIL_RECONCILED`
- Refund scope: `manual in v1` OR `supported in v1`
```

- [ ] **Step 2: Mark the final go/no-go result explicitly**

In `paypal-reader-for-woocommerce-m1-spike/evidence/m1/go-no-go.md`, replace the checklist heading with one of these exact outcomes:

```md
# GO
```

or

```md
# NO-GO
```

Rules:
- choose `# GO` only if every M1 hard exit criterion from the design spec is satisfied
- choose `# NO-GO` if any one criterion is unresolved

- [ ] **Step 3: Verify the evidence pack is complete before stopping**

Run:

```bash
cd /Users/kilbot/Projects
rg -n "REPLACE_WITH_|TODO|TBD|NO-GO|GO" paypal-reader-for-woocommerce-m1-spike/evidence/m1 docs/superpowers/specs/2026-04-22-paypal-reader-for-woocommerce-design.md
```

Expected:
- no `REPLACE_WITH_`, `TODO`, or `TBD` markers remain
- exactly one of `# GO` or `# NO-GO` appears in `evidence/m1/go-no-go.md`

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/specs/2026-04-22-paypal-reader-for-woocommerce-design.md \
  paypal-reader-for-woocommerce-m1-spike/evidence/m1/go-no-go.md

git commit -m "docs: finalize M1 paypal reader decisions and gate M2 start"
```

- [ ] **Step 5: Stop and branch the next plan correctly**

If the result is `# NO-GO`:
- stop work
- do not create the production plugin repo
- open a follow-up design revision instead of implementation

If the result is `# GO`:
- stop this plan after the commit above
- create a new implementation plan for M2 production scaffolding and checkout MVP work
- do not improvise M2 code in this same plan

---

## Self-Review Notes

### Spec coverage
- Covers the M1 exit criteria: auth, scopes, linking, session config, WebSocket status, payment request, verification, reconciliation, retry rules, and refund decision.
- Keeps the production plugin repo blocked until the M1 gate is explicitly marked `GO`.
- Produces the evidence pack required by the design spec before any M2 work starts.

### Placeholder scan
- The plan avoids `TBD`/`TODO`/template markers in task steps.
- The final verification step still searches for unresolved marker text in case the worker introduces any during execution.

### Type consistency
- Uses the same core names throughout: `linkId`, `channelId`, `internalTraceId`, `PAYMENT_REQUEST`, `STATUS_REQUEST`, `PAYMENT_RESULT_RESPONSE`, `CARD_PAYMENT_UUID`, `reconciliation_required`.
