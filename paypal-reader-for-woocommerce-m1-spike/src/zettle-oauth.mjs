import { readRequiredEnv } from './env.mjs';

export function buildAssertionGrantBody({ clientId, assertion }) {
  return new URLSearchParams({
    grant_type: 'urn:ietf:params:oauth:grant-type:jwt-bearer',
    client_id: clientId,
    assertion,
  });
}

export async function fetchAccessToken(fetchImpl = fetch, source = process.env) {
  const { ZETTLE_CLIENT_ID, ZETTLE_ASSERTION } = readRequiredEnv(
    ['ZETTLE_CLIENT_ID', 'ZETTLE_ASSERTION'],
    source
  );
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
