# PayPal Reader for WooCommerce

PayPal Reader integration for WooCommerce / WCPOS.

## Setup

1. Install and activate the plugin in WordPress with WooCommerce.
2. Go to `WooCommerce > Settings > Payments > PayPal Reader`.
3. Enable the gateway, leave **Test mode** on, and enter your Zettle **Client ID** and **Assertion** (JWT) from the Zettle Developer Portal.
4. Save, then scroll to **Paired readers** at the bottom of the settings screen.

## Pairing a reader

1. On the PayPal Reader device, open `Settings → Link with a developer` to show the pairing code.
2. In **Paired readers → Pair a new reader**, enter the code and (optionally) a human-readable name.
3. Click **Pair reader**. The reader appears in the paired list and is ready to take payments.
4. Use **Unpair** to remove a reader you no longer want to use.

## Taking a payment

1. Go to an order-pay checkout URL (e.g. `?pay_for_order=true&key=…`).
2. Select a paired reader.
3. Click **Start reader payment**. The browser opens a WebSocket session to the reader and streams payment progress.
4. Tap/insert the card on the reader. When the reader reports `COMPLETED`, the server verifies the amount against the order total, records the Zettle `CARD_PAYMENT_UUID`, and the order is placed automatically.
5. **Cancel payment** sends a cancel request to the reader over the same WebSocket.

To go live, disable **Test mode** and swap your Zettle test merchant credentials for production ones. The endpoints and flow are identical — only the merchant account differs.

### CI / automated testing (mock reader)

For CI runs and automated tests where no physical or test reader is available, add the following to `wp-config.php` to activate the in-memory mock reader:

```php
define( 'PRWC_USE_MOCK_READER', true );
define( 'PRWC_MOCK_CANCEL_BEHAVIOR', 'canceled' ); // or 'too_late'
```

The mock path is intentionally hidden from the admin UI — merchants should use **Test mode** against a real Zettle test merchant account, not the mock.

## Local disposable WordPress smoke test

A Docker-based smoke environment is included in `e2e/`.

### Start and verify the full flow

```bash
./e2e/smoke-test.sh
```

Additional scenarios:

```bash
PRWC_SMOKE_SCENARIO=cancel PRWC_MOCK_CANCEL_BEHAVIOR=canceled ./e2e/smoke-test.sh
PRWC_SMOKE_SCENARIO=too-late-cancel PRWC_MOCK_CANCEL_BEHAVIOR=too_late ./e2e/smoke-test.sh
```

To verify the packaged zip instead of the source checkout:

```bash
./scripts/build-plugin-zip.sh
PRWC_PLUGIN_INSTALL_MODE=zip PRWC_SMOKE_SCENARIO=complete ./e2e/smoke-test.sh
```

This will:
- start WordPress + MariaDB
- install WooCommerce
- activate this plugin with the CI mock reader constant enabled in `wp-config.php`
- create a fresh pending order
- drive the mock reader AJAX flow to completion
- call the gateway `process_payment()` path inside WordPress
- verify that the order reaches a paid state

### Local site details

- Store URL: `http://localhost:8091`
- Admin login: `admin` / `admin`

## Build an installable zip

```bash
./scripts/build-plugin-zip.sh
```

This writes both:
- `dist/paypal-reader-for-woocommerce-<version>.zip`
- `dist/paypal-reader-for-woocommerce.zip`

## Notes

- Live payments go directly to Zettle's Reader Connect API (`reader-connect.zettle.com/v1/integrator`) over REST + WebSocket. The PHP side brokers pairing, opens the session, and confirms the final result; the browser drives the WebSocket.
- OAuth tokens are cached in a WP transient keyed by the configured client ID (TTL = Zettle `expires_in` minus a 30s grace window).
- The plugin never trusts the browser's reported amount: `confirm_payment` rejects results whose amount does not match the order total.
