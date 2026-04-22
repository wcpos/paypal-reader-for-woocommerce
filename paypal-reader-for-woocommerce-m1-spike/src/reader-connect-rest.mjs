const BASE_URL = 'https://reader-connect.zettle.com/v1/integrator';

export function buildConfigureSessionRequest({ linkId, channelId }) {
  return {
    links: {
      [linkId]: [channelId],
    },
  };
}

export async function readerConnectFetch(path, { token, method = 'GET', body } = {}, fetchImpl = fetch) {
  const hasBody = body !== undefined;
  const response = await fetchImpl(`${BASE_URL}${path}`, {
    method,
    headers: {
      authorization: `Bearer ${token}`,
      ...(hasBody ? { 'content-type': 'application/json' } : {}),
    },
    body: hasBody ? JSON.stringify(body) : undefined,
  });

  if (!response.ok) {
    throw new Error(`Reader Connect request failed with ${response.status}`);
  }

  if (response.status === 204 || response.status === 205) {
    return undefined;
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
