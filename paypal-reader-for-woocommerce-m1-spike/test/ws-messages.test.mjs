import test from 'node:test';
import assert from 'node:assert/strict';
import {
  buildStatusRequestMessage,
  buildPaymentRequestMessage,
  buildCancelPaymentMessage,
} from '../src/ws-messages.mjs';

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

test('buildPaymentRequestMessage follows the Reader Connect payload shape from the docs', () => {
  assert.deepEqual(
    buildPaymentRequestMessage({
      linkId: 'link-123',
      channelId: 'wcpos-m1',
      messageId: 'msg-2',
      accessToken: 'token-123',
      expiresAt: 1700000000000,
      internalTraceId: 'attempt-123',
      amount: 100,
    }),
    {
      type: 'MESSAGE',
      linkId: 'link-123',
      channelId: 'wcpos-m1',
      messageId: 'msg-2',
      payload: {
        type: 'PAYMENT_REQUEST',
        accessToken: 'token-123',
        expiresAt: 1700000000000,
        internalTraceId: 'attempt-123',
        amount: '100',
        tippingType: 'NONE',
      },
    }
  );
});

test('buildCancelPaymentMessage includes the payment internalTraceId', () => {
  assert.deepEqual(
    buildCancelPaymentMessage({
      linkId: 'link-123',
      channelId: 'wcpos-m1',
      messageId: 'msg-3',
      internalTraceId: 'attempt-123',
    }),
    {
      type: 'MESSAGE',
      linkId: 'link-123',
      channelId: 'wcpos-m1',
      messageId: 'msg-3',
      payload: {
        type: 'CANCEL_PAYMENT_REQUEST',
        internalTraceId: 'attempt-123',
      },
    }
  );
});
