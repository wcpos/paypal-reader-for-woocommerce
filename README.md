# PayPal Reader for WooCommerce

Mock-first PayPal Reader integration for WooCommerce / WCPOS.

## Current testing path

This plugin is designed to be testable without a physical reader.

1. Install and activate the plugin in WordPress with WooCommerce.
2. Go to `WooCommerce > Settings > Payments > PayPal Reader`.
3. Leave the gateway in **Mock mode**.
4. Set an optional mock reader name and cancel behavior.
5. Use the gateway on an `order-pay` checkout flow and complete the simulated reader flow.

## Local disposable WordPress smoke test

A Docker-based smoke environment is included in `e2e/`.

### Start and verify the full flow

```bash
./e2e/smoke-test.sh
```

This will:
- start WordPress + MariaDB
- install WooCommerce
- activate this plugin in mock mode
- create a fresh pending order
- drive the mock reader AJAX flow to completion
- call the gateway `process_payment()` path inside WordPress
- verify that the order reaches a paid state

### Local site details

- Store URL: `http://localhost:8091`
- Admin login: `admin` / `admin`

## Notes

- Live Zettle credentials are optional and currently fall back to the built-in mock flow.
- The repository still contains the `paypal-reader-for-woocommerce-m1-spike` discovery workspace for protocol research.
