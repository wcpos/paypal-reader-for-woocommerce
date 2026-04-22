import test from 'node:test';
import assert from 'node:assert/strict';

import { fetchAccessToken } from '../src/zettle-oauth.mjs';
import {
  claimLinkOffer,
  configureSession,
  getLinks,
} from '../src/reader-connect-rest.mjs';
import {
  buildCancelPaymentMessage,
  buildPaymentRequestMessage,
  buildStatusRequestMessage,
} from '../src/ws-messages.mjs';
import { createMockReaderConnectServer } from '../src/mock-reader-connect.mjs';

test('createMockReaderConnectServer supports the documented oauth and reader-connect REST flow', async () => {
  const server = createMockReaderConnectServer();
  const token = await fetchAccessToken(server.fetch, {
    ZETTLE_CLIENT_ID: 'client-123',
    ZETTLE_ASSERTION: 'assertion-456',
  });

  assert.equal(token.access_token, 'mock-access-token');
  assert.equal(token.expires_in, 7200);

  const link = await claimLinkOffer(
    {
      token: token.access_token,
      code: 'ERFS2342',
      deviceName: 'Counter Reader',
    },
    server.fetch,
  );

  assert.deepEqual(link, {
    id: '497f6eca-6276-4993-bfeb-53cbbbba6f08',
    organizationUuid: '534facdea-56df-ca24-edc2-46cbfdba12454',
    readerTags: {
      model: 'PayPal Reader',
      serialNumber: '3321900308',
    },
    integratorTags: {
      deviceName: 'Counter Reader',
      welcomeMessage: 'Hello world!',
    },
  });

  const links = await getLinks({ token: token.access_token }, server.fetch);
  assert.deepEqual(links, [link]);

  const session = await configureSession(
    {
      token: token.access_token,
      linkId: link.id,
      channelId: 'wcpos-m1',
    },
    server.fetch,
  );

  assert.deepEqual(session, {
    location: 'wss://reader-connect-ws.zettle.com/integrator?ticketSecret=mock-ticket-secret',
    authorized: {
      '497f6eca-6276-4993-bfeb-53cbbbba6f08': ['wcpos-m1'],
    },
    failed: {},
  });
});

test('createMockReaderConnectServer returns a realistic status and successful payment transcript', () => {
  const server = createMockReaderConnectServer();
  const statusRequest = buildStatusRequestMessage({
    linkId: '497f6eca-6276-4993-bfeb-53cbbbba6f08',
    channelId: 'wcpos-m1',
    messageId: 'status-1',
  });
  const paymentRequest = buildPaymentRequestMessage({
    linkId: '497f6eca-6276-4993-bfeb-53cbbbba6f08',
    channelId: 'wcpos-m1',
    messageId: 'payment-1',
    accessToken: 'mock-access-token',
    expiresAt: 4070908800000,
    internalTraceId: 'attempt-123',
    amount: 1444,
  });

  const frames = server.exchange(statusRequest, paymentRequest);

  assert.deepEqual(frames[0], {
    origin: 'READER_CONNECT',
    type: 'MESSAGE',
    linkId: '497f6eca-6276-4993-bfeb-53cbbbba6f08',
    channelId: 'wcpos-m1',
    messageId: 'status-1',
    payload: {
      type: 'STATUS_RESPONSE',
      status: 'READY',
    },
  });

  assert.deepEqual(
    frames.slice(1, -1).map((frame) => frame.payload.paymentProgress),
    [
      'STARTING_TRANSACTION',
      'PREPARING',
      'INITIALIZING',
      'PRESENT_CARD',
      'CARD_PRESENTED',
      'AUTHORIZING',
      'APPROVED',
      'COMPLETED',
    ],
  );

  assert.deepEqual(frames.at(-1), {
    origin: 'READER_CONNECT',
    type: 'MESSAGE',
    linkId: '497f6eca-6276-4993-bfeb-53cbbbba6f08',
    channelId: 'wcpos-m1',
    messageId: 'payment-1',
    payload: {
      type: 'PAYMENT_RESULT_RESPONSE',
      internalTraceId: 'attempt-123',
      resultStatus: 'COMPLETED',
      resultPayload: {
        amount: '1444',
        currency: 'USD',
        gratuityAmount: '0',
        reference: 'WCPOS-MOCK-ORDER-1001',
        trackingId: 'mock-tracking-id',
        checkoutUUID: 'mock-checkout-uuid',
        CARD_PAYMENT_UUID: 'mock-card-payment-uuid',
      },
    },
  });
});

test('createMockReaderConnectServer can model the docs warning that cancel does not guarantee cancellation', () => {
  const server = createMockReaderConnectServer({ cancelBehavior: 'too_late' });
  const paymentRequest = buildPaymentRequestMessage({
    linkId: '497f6eca-6276-4993-bfeb-53cbbbba6f08',
    channelId: 'wcpos-m1',
    messageId: 'payment-2',
    accessToken: 'mock-access-token',
    expiresAt: 4070908800000,
    internalTraceId: 'attempt-cancel-too-late',
    amount: 1444,
  });
  const cancelRequest = buildCancelPaymentMessage({
    linkId: '497f6eca-6276-4993-bfeb-53cbbbba6f08',
    channelId: 'wcpos-m1',
    messageId: 'cancel-1',
    internalTraceId: 'attempt-cancel-too-late',
  });

  const frames = server.exchange(paymentRequest, cancelRequest);

  assert.equal(frames.at(-1).payload.type, 'PAYMENT_RESULT_RESPONSE');
  assert.equal(frames.at(-1).payload.internalTraceId, 'attempt-cancel-too-late');
  assert.equal(frames.at(-1).payload.resultStatus, 'COMPLETED');
});
