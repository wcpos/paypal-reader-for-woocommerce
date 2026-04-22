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
