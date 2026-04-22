# PayPal Reader for WooCommerce

PayPal Reader integration for WooCommerce / WCPOS.

## Testing the gateway

1. Install and activate the plugin in WordPress with WooCommerce.
2. Go to `WooCommerce > Settings > Payments > PayPal Reader`.
3. Leave **Test mode** enabled and enter your Zettle developer / test merchant credentials.
4. Use the gateway on an `order-pay` checkout flow and complete the reader flow.

To go live, disable **Test mode** and swap in your production Zettle credentials.

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

- Live Zettle credentials are optional and currently fall back to the built-in mock flow.
- The repository still contains the `paypal-reader-for-woocommerce-m1-spike` discovery workspace for protocol research.
