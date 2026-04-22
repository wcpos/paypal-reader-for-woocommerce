(function () {
  const config = window.paypalReaderData || null;
  const root = document.querySelector('.paypal-reader-terminal');

  if (!config || !root) {
    return;
  }

  const strings = config.strings || {};
  const statusNode = root.querySelector('.paypal-reader-terminal__status');
  const readersNode = root.querySelector('.paypal-reader-terminal__readers');
  const logNode = root.querySelector('.paypal-reader-terminal__log');
  const startButton = root.querySelector('.paypal-reader-terminal__start');
  const cancelButton = root.querySelector('.paypal-reader-terminal__cancel');

  let selectedReaderId = null;
  let transport = null;
  let pollTimer = null;
  let socket = null;

  if (config.testMode) {
    const badge = document.createElement('span');
    badge.className = 'paypal-reader-terminal__test-badge';
    badge.textContent = strings.testMode || 'TEST MODE';
    root.insertBefore(badge, root.firstChild);
  }

  function log(message) {
    if (!logNode) return;
    logNode.textContent += `[${new Date().toLocaleTimeString()}] ${message}\n`;
    logNode.scrollTop = logNode.scrollHeight;
  }

  function setStatus(message) {
    if (statusNode) {
      statusNode.textContent = message;
    }
    log(message);
  }

  async function request(action, extra) {
    const body = new URLSearchParams(Object.assign({
      action: action,
      nonce: config.nonce,
      order_id: String(config.orderId),
      order_key: config.orderKey,
    }, extra || {}));

    const response = await fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
    });

    let payload;
    try {
      payload = await response.json();
    } catch (e) {
      throw new Error('Invalid server response.');
    }
    if (!payload || !payload.success) {
      const msg = payload && typeof payload.data === 'string'
        ? payload.data
        : (payload && payload.data && payload.data.message) || 'Request failed.';
      throw new Error(msg);
    }
    return payload.data;
  }

  function renderReaders(readers) {
    if (!readersNode) return;
    readersNode.innerHTML = '';

    if (!readers.length) {
      const empty = document.createElement('p');
      empty.className = 'paypal-reader-terminal__empty';
      empty.textContent = strings.noReaders || 'No readers paired yet.';
      readersNode.appendChild(empty);
      if (startButton) startButton.disabled = true;
      return;
    }

    readers.forEach((reader, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'button paypal-reader-terminal__reader';
      button.textContent = reader.label + (reader.status ? ` (${reader.status})` : '');
      button.addEventListener('click', () => {
        selectedReaderId = reader.id;
        Array.from(readersNode.querySelectorAll('button')).forEach((node) => node.classList.remove('is-selected'));
        button.classList.add('is-selected');
        setStatus(`Selected ${reader.label}`);
      });

      if (index === 0) {
        selectedReaderId = reader.id;
        button.classList.add('is-selected');
      }
      readersNode.appendChild(button);
    });
  }

  async function loadReaders() {
    setStatus(strings.loadingReaders || 'Loading readers…');
    const data = await request('paypal_reader_get_readers', {});
    transport = data.transport || 'mock';
    renderReaders(data.readers || []);
    setStatus(`Mode: ${data.mode}. Ready to start payment.`);
  }

  function submitCheckout() {
    const placeOrder = document.querySelector('#place_order');
    if (placeOrder) {
      placeOrder.click();
    }
  }

  function resetButtons() {
    if (startButton) startButton.disabled = false;
    if (cancelButton) cancelButton.disabled = true;
  }

  // ---- Mock transport (CI only) ---------------------------------------
  async function checkMockPaymentStatus() {
    const status = await request('paypal_reader_check_payment_status', {});
    const progress = status.current_progress || status.state;
    setStatus(`Payment status: ${progress}`);

    if (status.state === 'completed') {
      clearInterval(pollTimer);
      pollTimer = null;
      resetButtons();
      setStatus(strings.paymentComplete || 'Payment complete. Submitting order…');
      setTimeout(submitCheckout, 400);
    } else if (status.state === 'canceled') {
      clearInterval(pollTimer);
      pollTimer = null;
      resetButtons();
      setStatus(strings.paymentCanceled || 'Payment canceled.');
    }
  }

  async function startMockPayment() {
    const started = await request('paypal_reader_start_payment', {
      reader_id: selectedReaderId,
      amount: String(config.amount),
    });
    setStatus(`Started attempt ${started.attempt_id}`);

    clearInterval(pollTimer);
    pollTimer = setInterval(() => {
      checkMockPaymentStatus().catch((error) => setStatus(error.message));
    }, 1000);
  }

  // ---- Zettle WebSocket transport -------------------------------------
  function closeSocket() {
    if (socket) {
      try { socket.close(); } catch (e) { /* noop */ }
      socket = null;
    }
  }

  function sendSocket(message) {
    if (!socket || socket.readyState !== WebSocket.OPEN) {
      return;
    }
    socket.send(JSON.stringify(message));
  }

  async function confirmPayment(result) {
    try {
      await request('paypal_reader_confirm_payment', {
        result: JSON.stringify(result),
      });
      setStatus(strings.paymentComplete || 'Payment complete. Submitting order…');
      setTimeout(submitCheckout, 400);
    } catch (error) {
      setStatus(`${strings.paymentFailed || 'Payment failed.'} ${error.message}`);
      resetButtons();
    }
  }

  function openZettleSocket(session) {
    closeSocket();
    setStatus(strings.connectingReader || 'Connecting to reader…');

    socket = new WebSocket(session.wsUrl);

    socket.addEventListener('open', () => {
      // Query the reader state; PAYMENT_REQUEST should only be sent once
      // the reader reports READY.
      sendSocket({ messageType: 'STATUS_REQUEST' });
    });

    socket.addEventListener('error', () => {
      setStatus(strings.paymentFailed || 'Payment failed.');
      resetButtons();
    });

    socket.addEventListener('close', () => {
      socket = null;
    });

    socket.addEventListener('message', (event) => {
      let message;
      try {
        message = JSON.parse(event.data);
      } catch (e) {
        log('Unparseable message: ' + event.data);
        return;
      }

      const type = message.messageType || message.type || '';
      const payload = message.messagePayload || message.payload || message;

      switch (type) {
        case 'STATUS_RESPONSE': {
          const state = payload.readerState || payload.state || '';
          log(`Reader state: ${state}`);
          if (state === 'READY' || state === 'IDLE') {
            setStatus(strings.readerReady || 'Reader ready. Requesting payment…');
            sendSocket({
              messageType: 'PAYMENT_REQUEST',
              messagePayload: {
                accessToken: session.accessToken,
                expiresAt: session.expiresAt,
                internalTraceId: session.internalTraceId,
                amount: String(session.amount),
                currency: session.currency,
                tippingType: session.tippingType || 'NONE',
              },
            });
          }
          break;
        }

        case 'PAYMENT_PROGRESS':
        case 'PAYMENT_IN_PROGRESS':
        case 'PRESENT_CARD':
        case 'PROCESSING':
        case 'SIGNATURE_REQUIRED': {
          const stage = payload.progressName || payload.stage || type;
          setStatus(`${strings.paymentInProgress || 'Payment in progress…'} (${stage})`);
          break;
        }

        case 'PAYMENT_RESULT_RESPONSE':
        case 'PAYMENT_RESULT': {
          closeSocket();
          if (cancelButton) cancelButton.disabled = true;
          const resultStatus = payload.resultStatus || payload.status || '';
          if (resultStatus === 'COMPLETED') {
            confirmPayment(payload);
          } else if (resultStatus === 'CANCELLED' || resultStatus === 'CANCELED') {
            setStatus(strings.paymentCanceled || 'Payment canceled.');
            resetButtons();
          } else {
            const reason = payload.failureReason || payload.reason || resultStatus || 'unknown';
            setStatus(`${strings.paymentFailed || 'Payment failed.'} (${reason})`);
            resetButtons();
          }
          break;
        }

        case 'ERROR':
        case 'ERROR_RESPONSE': {
          const reason = payload.reason || payload.message || 'error';
          setStatus(`${strings.paymentFailed || 'Payment failed.'} (${reason})`);
          closeSocket();
          resetButtons();
          break;
        }

        default:
          log(`Message: ${type}`);
      }
    });
  }

  async function startZettlePayment() {
    const session = await request('paypal_reader_start_payment', {
      reader_id: selectedReaderId,
      amount: String(config.amount),
    });
    openZettleSocket(session);
  }

  // ---- Button wiring ---------------------------------------------------
  if (startButton) {
    startButton.addEventListener('click', async () => {
      try {
        if (!selectedReaderId) {
          throw new Error(strings.selectReader || 'Select a reader first.');
        }
        startButton.disabled = true;
        if (cancelButton) cancelButton.disabled = false;

        if (transport === 'zettle-ws') {
          await startZettlePayment();
        } else {
          await startMockPayment();
        }
      } catch (error) {
        resetButtons();
        setStatus(error.message);
      }
    });
  }

  if (cancelButton) {
    cancelButton.addEventListener('click', async () => {
      try {
        if (transport === 'zettle-ws' && socket && socket.readyState === WebSocket.OPEN) {
          sendSocket({ messageType: 'CANCEL_PAYMENT_REQUEST' });
        }

        const result = await request('paypal_reader_cancel_payment', {});
        setStatus(result.cancel_behavior ? `Cancel result: ${result.cancel_behavior}` : (strings.paymentCanceled || 'Payment canceled.'));

        if ((result.state || 'canceled') === 'canceled') {
          clearInterval(pollTimer);
          pollTimer = null;
          closeSocket();
          resetButtons();
        }
      } catch (error) {
        setStatus(error.message);
      }
    });
  }

  loadReaders().catch((error) => setStatus(error.message));
}());
