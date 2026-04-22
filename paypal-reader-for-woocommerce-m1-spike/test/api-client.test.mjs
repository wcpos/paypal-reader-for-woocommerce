import test from 'node:test';
import assert from 'node:assert/strict';
import { buildAssertionGrantBody, fetchAccessToken } from '../src/zettle-oauth.mjs';
import {
  buildConfigureSessionRequest,
  claimLinkOffer,
  deleteLink,
  readerConnectFetch,
} from '../src/reader-connect-rest.mjs';

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

test('fetchAccessToken posts the assertion grant to the token endpoint', async () => {
  const calls = [];
  const fetchImpl = async (url, options) => {
    calls.push({ url, options });
    return {
      ok: true,
      status: 200,
      async json() {
        return { access_token: 'token-123' };
      },
    };
  };

  await fetchAccessToken(fetchImpl, {
    ZETTLE_CLIENT_ID: 'client-123',
    ZETTLE_ASSERTION: 'assertion-456',
  });

  assert.equal(calls.length, 1);
  assert.deepEqual(calls[0], {
    url: 'https://oauth.zettle.com/token',
    options: {
      method: 'POST',
      headers: {
        'content-type': 'application/x-www-form-urlencoded',
      },
      body: buildAssertionGrantBody({
        clientId: 'client-123',
        assertion: 'assertion-456',
      }),
    },
  });
});

test('readerConnectFetch posts json with authorization and serializes the body', async () => {
  const calls = [];
  const fetchImpl = async (url, options) => {
    calls.push({ url, options });
    return {
      ok: true,
      status: 200,
      async json() {
        return { ok: true };
      },
    };
  };

  await readerConnectFetch(
    '/sessions',
    {
      token: 'token-123',
      method: 'POST',
      body: {
        links: {
          'link-123': ['wcpos-m1'],
        },
      },
    },
    fetchImpl
  );

  assert.equal(calls.length, 1);
  assert.deepEqual(calls[0], {
    url: 'https://reader-connect.zettle.com/v1/integrator/sessions',
    options: {
      method: 'POST',
      headers: {
        authorization: 'Bearer token-123',
        'content-type': 'application/json',
      },
      body: JSON.stringify({
        links: {
          'link-123': ['wcpos-m1'],
        },
      }),
    },
  });
});

test('claimLinkOffer includes the link-offer claim payload', async () => {
  const calls = [];
  const fetchImpl = async (url, options) => {
    calls.push({ url, options });
    return {
      ok: true,
      status: 200,
      async json() {
        return { id: 'link-123' };
      },
    };
  };

  await claimLinkOffer(
    {
      token: 'token-123',
      code: 'PAIR-456',
      deviceName: 'Reader 1',
    },
    fetchImpl
  );

  assert.equal(calls.length, 1);
  assert.deepEqual(calls[0], {
    url: 'https://reader-connect.zettle.com/v1/integrator/link-offers/claim',
    options: {
      method: 'POST',
      headers: {
        authorization: 'Bearer token-123',
        'content-type': 'application/json',
      },
      body: JSON.stringify({
        code: 'PAIR-456',
        tags: {
          deviceName: 'Reader 1',
        },
      }),
    },
  });
});

test('readerConnectFetch throws on non-ok responses', async () => {
  const fetchImpl = async () => ({
    ok: false,
    status: 500,
    async json() {
      throw new Error('should not read body');
    },
  });

  await assert.rejects(
    readerConnectFetch('/links', { token: 'token-123' }, fetchImpl),
    /Reader Connect request failed with 500/
  );
});

test('deleteLink resolves without parsing json when the response is empty', async () => {
  let jsonCalled = false;
  const calls = [];
  const fetchImpl = async (url, options) => {
    calls.push({ url, options });
    return {
      ok: true,
      status: 204,
      async json() {
        jsonCalled = true;
        throw new Error('json should not be called for empty responses');
      },
    };
  };

  await assert.doesNotReject(deleteLink({ token: 'token-123', linkId: 'link-123' }, fetchImpl));
  assert.deepEqual(calls[0], {
    url: 'https://reader-connect.zettle.com/v1/integrator/links/link-123',
    options: {
      method: 'DELETE',
      headers: {
        authorization: 'Bearer token-123',
      },
      body: undefined,
    },
  });
  assert.equal(jsonCalled, false);
});
