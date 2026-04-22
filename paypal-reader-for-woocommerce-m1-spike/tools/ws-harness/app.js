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

function sendMessage(label, payload) {
  if (!socket || socket.readyState !== WebSocket.OPEN) {
    log(`${label} NOT SENT`, {
      message: 'WebSocket is not connected.',
    });
    return;
  }

  socket.send(JSON.stringify(payload));
  log(`SENT ${label}`, payload);
}

document.querySelector('#connect').addEventListener('click', () => {
  socket = new WebSocket(field('location'));
  socket.addEventListener('open', () => log('OPEN', { location: field('location') }));
  socket.addEventListener('message', (event) => {
    try {
      log('MESSAGE', JSON.parse(event.data));
    } catch {
      log('MESSAGE (non-JSON)', { data: String(event.data) });
    }
  });
  socket.addEventListener('close', () => log('CLOSE', {}));
  socket.addEventListener('error', () => log('ERROR', {}));
});

document.querySelector('#status').addEventListener('click', () => {
  const payload = buildStatusRequestMessage({
    linkId: field('linkId'),
    channelId: field('channelId'),
    messageId: messageId(),
  });
  sendMessage('STATUS', payload);
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
  sendMessage('PAYMENT', payload);
});

document.querySelector('#cancel').addEventListener('click', () => {
  const payload = buildCancelPaymentMessage({
    linkId: field('linkId'),
    channelId: field('channelId'),
    messageId: messageId(),
    internalTraceId: field('attemptId'),
  });
  sendMessage('CANCEL', payload);
});
