(function () {
  const data = window.paypalReaderAdminData || null;
  const root = document.querySelector('.paypal-reader-pairing');
  if (!data || !root) {
    return;
  }

  const strings = data.strings || {};
  const nonce = root.getAttribute('data-nonce') || '';
  const list = root.querySelector('.paypal-reader-pairing__list');
  const codeInput = root.querySelector('.paypal-reader-pairing__code');
  const nameInput = root.querySelector('.paypal-reader-pairing__name');
  const claimButton = root.querySelector('.paypal-reader-pairing__claim');
  const message = root.querySelector('.paypal-reader-pairing__message');

  function setMessage(text, isError) {
    if (!message) return;
    message.textContent = text || '';
    message.classList.toggle('is-error', Boolean(isError));
  }

  async function postAction(action, params) {
    const body = new URLSearchParams(Object.assign({ action: action, nonce: nonce }, params));
    const response = await fetch(data.ajaxUrl, {
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

  function removeEmptyRow() {
    const empty = list && list.querySelector('.paypal-reader-pairing__empty');
    if (empty) empty.remove();
  }

  function renderEmptyRowIfNeeded() {
    if (!list) return;
    if (list.querySelector('tr[data-link-id]')) return;
    const tr = document.createElement('tr');
    tr.className = 'paypal-reader-pairing__empty';
    const td = document.createElement('td');
    td.colSpan = 3;
    td.textContent = strings.noReaders || 'No readers paired yet.';
    tr.appendChild(td);
    list.appendChild(tr);
  }

  function appendLinkRow(link) {
    if (!list) return;
    const tr = document.createElement('tr');
    tr.setAttribute('data-link-id', link.linkId);

    const label = document.createElement('td');
    label.textContent = link.label || link.linkId;

    const idCell = document.createElement('td');
    const code = document.createElement('code');
    code.textContent = link.linkId;
    idCell.appendChild(code);

    const actions = document.createElement('td');
    const unpair = document.createElement('button');
    unpair.type = 'button';
    unpair.className = 'button paypal-reader-pairing__unpair';
    unpair.textContent = strings.unpair || 'Unpair';
    actions.appendChild(unpair);

    tr.appendChild(label);
    tr.appendChild(idCell);
    tr.appendChild(actions);
    list.appendChild(tr);
  }

  if (claimButton) {
    claimButton.addEventListener('click', async () => {
      const code = (codeInput && codeInput.value || '').trim();
      const name = (nameInput && nameInput.value || '').trim();
      if (code === '') {
        setMessage(strings.enterCode || 'Enter the pairing code first.', true);
        return;
      }

      claimButton.disabled = true;
      setMessage(strings.pairing || 'Pairing reader…', false);

      try {
        const link = await postAction('paypal_reader_pair_reader', {
          code: code,
          device_name: name,
        });
        removeEmptyRow();
        appendLinkRow(link);
        if (codeInput) codeInput.value = '';
        if (nameInput) nameInput.value = '';
        setMessage(strings.paired || 'Reader paired.', false);
      } catch (error) {
        setMessage(error.message, true);
      } finally {
        claimButton.disabled = false;
      }
    });
  }

  if (list) {
    list.addEventListener('click', async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      if (!target.classList.contains('paypal-reader-pairing__unpair')) return;

      const row = target.closest('tr[data-link-id]');
      if (!row) return;
      const linkId = row.getAttribute('data-link-id') || '';
      if (linkId === '') return;

      const confirmMsg = strings.confirmUnpair || 'Unpair this reader?';
      if (!window.confirm(confirmMsg)) return;

      target.disabled = true;
      setMessage(strings.unpairing || 'Unpairing reader…', false);

      try {
        await postAction('paypal_reader_unpair_reader', { link_id: linkId });
        row.remove();
        renderEmptyRowIfNeeded();
        setMessage(strings.unpaired || 'Reader unpaired.', false);
      } catch (error) {
        target.disabled = false;
        setMessage(error.message, true);
      }
    });
  }
}());
