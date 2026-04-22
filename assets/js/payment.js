(function () {
  const config = window.paypalReaderData || null;
  const root = document.querySelector('.paypal-reader-terminal');

  if (!config || !root) {
    return;
  }

  const statusNode = root.querySelector('.paypal-reader-terminal__status');
  const readersNode = root.querySelector('.paypal-reader-terminal__readers');
  const logNode = root.querySelector('.paypal-reader-terminal__log');
  const startButton = root.querySelector('.paypal-reader-terminal__start');
  const cancelButton = root.querySelector('.paypal-reader-terminal__cancel');

  let selectedReaderId = null;
  let pollTimer = null;

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
    const body = new URLSearchParams({
      action,
      nonce: config.nonce,
      order_id: String(config.orderId),
      order_key: config.orderKey,
      ...extra,
    });

    const response = await fetch(config.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: body.toString(),
    });

    const payload = await response.json();
    if (!payload.success) {
      throw new Error(typeof payload.data === 'string' ? payload.data : 'Request failed');
    }

    return payload.data;
  }

  function renderReaders(readers) {
    if (!readersNode) return;

    readersNode.innerHTML = '';
    readers.forEach((reader, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'button paypal-reader-terminal__reader';
      button.textContent = `${reader.label} (${reader.status})`;
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
    setStatus(config.strings.loadingReaders || 'Loading readers…');
    const data = await request('paypal_reader_get_readers', {});
    renderReaders(data.readers || []);
    setStatus(`Mode: ${data.mode}. Ready to start payment.`);
  }

  async function checkPaymentStatus() {
    const status = await request('paypal_reader_check_payment_status', {});
    const progress = status.current_progress || status.state;
    setStatus(`Payment status: ${progress}`);

    if (status.state === 'completed') {
      clearInterval(pollTimer);
      pollTimer = null;
      cancelButton.disabled = true;
      startButton.disabled = false;
      setStatus(config.strings.paymentComplete || 'Payment complete. Submitting order…');
      setTimeout(() => {
        const placeOrder = document.querySelector('#place_order');
        if (placeOrder) {
          placeOrder.click();
        }
      }, 500);
    }

    if (status.state === 'canceled') {
      clearInterval(pollTimer);
      pollTimer = null;
      cancelButton.disabled = true;
      startButton.disabled = false;
      setStatus(config.strings.paymentCanceled || 'Payment canceled.');
    }
  }

  startButton.addEventListener('click', async () => {
    try {
      if (!selectedReaderId) {
        throw new Error(config.strings.selectReader || 'Select a reader first.');
      }

      startButton.disabled = true;
      cancelButton.disabled = false;
      const started = await request('paypal_reader_start_payment', {
        reader_id: selectedReaderId,
        amount: String(config.amount),
      });
      setStatus(`Started attempt ${started.attempt_id}`);

      clearInterval(pollTimer);
      pollTimer = setInterval(() => {
        checkPaymentStatus().catch((error) => setStatus(error.message));
      }, 1000);
    } catch (error) {
      startButton.disabled = false;
      cancelButton.disabled = true;
      setStatus(error.message);
    }
  });

  cancelButton.addEventListener('click', async () => {
    try {
      const result = await request('paypal_reader_cancel_payment', {});
      setStatus(`Cancel result: ${result.cancel_behavior}`);
      if (result.state === 'canceled') {
        cancelButton.disabled = true;
        startButton.disabled = false;
        clearInterval(pollTimer);
        pollTimer = null;
      }
    } catch (error) {
      setStatus(error.message);
    }
  });

  loadReaders().catch((error) => setStatus(error.message));
}());
