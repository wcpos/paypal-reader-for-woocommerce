const OAUTH_BASE_URL = 'https://oauth.zettle.com';
const READER_CONNECT_BASE_URL = 'https://reader-connect.zettle.com/v1/integrator';

const DEFAULTS = {
  accessToken: 'mock-access-token',
  ticketSecret: 'mock-ticket-secret',
  organizationUuid: '534facdea-56df-ca24-edc2-46cbfdba12454',
  linkId: '497f6eca-6276-4993-bfeb-53cbbbba6f08',
  readerModel: 'PayPal Reader',
  readerSerialNumber: '3321900308',
  welcomeMessage: 'Hello world!',
  currency: 'USD',
  cancelBehavior: 'canceled',
  successfulPaymentProgress: [
    'STARTING_TRANSACTION',
    'PREPARING',
    'INITIALIZING',
    'PRESENT_CARD',
    'CARD_PRESENTED',
    'AUTHORIZING',
    'APPROVED',
    'COMPLETED',
  ],
  preCancelProgress: ['STARTING_TRANSACTION', 'PREPARING', 'INITIALIZING', 'PRESENT_CARD'],
};

function jsonResponse(status, value) {
  return {
    ok: status >= 200 && status < 300,
    status,
    async json() {
      return structuredClone(value);
    },
  };
}

function getJsonBody(body) {
  if (body === undefined || body === null) {
    return undefined;
  }

  if (typeof body === 'string') {
    return JSON.parse(body);
  }

  if (typeof body === 'object' && typeof body.toString === 'function' && body.constructor?.name === 'URLSearchParams') {
    return Object.fromEntries(body.entries());
  }

  return body;
}

function requireBearerToken(options, expectedToken) {
  const authorization = options?.headers?.authorization;

  if (authorization !== `Bearer ${expectedToken}`) {
    return jsonResponse(401, {
      error: 'unauthorized',
    });
  }

  return null;
}

function buildLink(config, deviceName = 'Mock Reader') {
  return {
    id: config.linkId,
    organizationUuid: config.organizationUuid,
    readerTags: {
      model: config.readerModel,
      serialNumber: config.readerSerialNumber,
    },
    integratorTags: {
      deviceName,
      welcomeMessage: config.welcomeMessage,
    },
  };
}

function buildEnvelope(request, payload) {
  return {
    origin: 'READER_CONNECT',
    type: 'MESSAGE',
    linkId: request.linkId,
    channelId: request.channelId,
    messageId: request.messageId,
    payload,
  };
}

function buildPaymentResultPayload(request, config) {
  return {
    type: 'PAYMENT_RESULT_RESPONSE',
    internalTraceId: request.payload.internalTraceId,
    resultStatus: 'COMPLETED',
    resultPayload: {
      amount: request.payload.amount,
      currency: config.currency,
      gratuityAmount: '0',
      reference: 'WCPOS-MOCK-ORDER-1001',
      trackingId: 'mock-tracking-id',
      checkoutUUID: 'mock-checkout-uuid',
      CARD_PAYMENT_UUID: 'mock-card-payment-uuid',
    },
  };
}

function buildCanceledResultPayload(request) {
  return {
    type: 'PAYMENT_RESULT_RESPONSE',
    internalTraceId: request.payload.internalTraceId,
    resultStatus: 'CANCELED',
    resultErrorMessage: 'Canceled by merchant request',
  };
}

function buildPaymentProgressFrames(request, progressList) {
  return progressList.map((paymentProgress) =>
    buildEnvelope(request, {
      type: 'PAYMENT_PROGRESS_RESPONSE',
      internalTraceId: request.payload.internalTraceId,
      paymentProgress,
    })
  );
}

export function createMockReaderConnectServer(options = {}) {
  const config = {
    ...DEFAULTS,
    ...options,
  };
  const links = new Map();

  return {
    async fetch(url, requestOptions = {}) {
      const target = new URL(url);

      if (target.origin === OAUTH_BASE_URL && target.pathname === '/token') {
        const formBody = getJsonBody(requestOptions.body);
        if (
          formBody?.grant_type !== 'urn:ietf:params:oauth:grant-type:jwt-bearer' ||
          !formBody?.client_id ||
          !formBody?.assertion
        ) {
          return jsonResponse(400, {
            error: 'invalid_request',
          });
        }

        return jsonResponse(200, {
          access_token: config.accessToken,
          token_type: 'Bearer',
          expires_in: 7200,
          scope: 'READ:USERINFO WRITE:USERINFO READ:PAYMENT WRITE:PAYMENT',
        });
      }

      if (!target.href.startsWith(READER_CONNECT_BASE_URL)) {
        return jsonResponse(404, {
          error: 'not_found',
        });
      }

      const unauthorized = requireBearerToken(requestOptions, config.accessToken);
      if (unauthorized) {
        return unauthorized;
      }

      const path = target.pathname.replace('/v1/integrator', '');
      const body = getJsonBody(requestOptions.body);

      if (requestOptions.method === 'POST' && path === '/link-offers/claim') {
        const link = buildLink(config, body?.tags?.deviceName);
        links.set(link.id, link);
        return jsonResponse(200, link);
      }

      if ((requestOptions.method ?? 'GET') === 'GET' && path === '/links') {
        return jsonResponse(200, [...links.values()]);
      }

      if (requestOptions.method === 'DELETE' && path.startsWith('/links/')) {
        links.delete(path.split('/').at(-1));
        return jsonResponse(200, {
          deleted: true,
        });
      }

      if (requestOptions.method === 'POST' && path === '/sessions') {
        const authorized = body?.links ?? {};
        return jsonResponse(200, {
          location: `wss://reader-connect-ws.zettle.com/integrator?ticketSecret=${config.ticketSecret}`,
          authorized,
          failed: {},
        });
      }

      return jsonResponse(404, {
        error: 'not_found',
      });
    },

    exchange(...messages) {
      const cancelByTraceId = new Map(
        messages
          .filter((message) => message?.payload?.type === 'CANCEL_PAYMENT_REQUEST')
          .map((message) => [message.payload.internalTraceId, message]),
      );

      return messages.flatMap((message) => {
        const payloadType = message?.payload?.type;

        if (payloadType === 'STATUS_REQUEST') {
          return [
            buildEnvelope(message, {
              type: 'STATUS_RESPONSE',
              status: 'READY',
            }),
          ];
        }

        if (payloadType === 'PAYMENT_REQUEST') {
          const relatedCancel = cancelByTraceId.get(message.payload.internalTraceId);

          if (relatedCancel && config.cancelBehavior === 'canceled') {
            return [
              ...buildPaymentProgressFrames(message, config.preCancelProgress),
              buildEnvelope(relatedCancel, {
                type: 'PAYMENT_PROGRESS_RESPONSE',
                internalTraceId: message.payload.internalTraceId,
                paymentProgress: 'CANCELING',
              }),
              buildEnvelope(relatedCancel, buildCanceledResultPayload(relatedCancel)),
            ];
          }

          return [
            ...buildPaymentProgressFrames(message, config.successfulPaymentProgress),
            buildEnvelope(message, buildPaymentResultPayload(message, config)),
          ];
        }

        if (payloadType === 'CANCEL_PAYMENT_REQUEST') {
          return [];
        }

        return [];
      });
    },
  };
}
