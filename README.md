# PayPal Reader for WooCommerce

Mock-first PayPal Reader integration for WooCommerce / WCPOS.

## Current testing path

This plugin is designed to be testable without a physical reader.

1. Install and activate the plugin in WordPress with WooCommerce.
2. Go to `WooCommerce > Settings > Payments > PayPal Reader`.
3. Leave the gateway in **Mock mode**.
4. Set an optional mock reader name and cancel behavior.
5. Use the gateway on an `order-pay` checkout flow and complete the simulated reader flow.

## Notes

- Live Zettle credentials are optional and currently fall back to the built-in mock flow.
- The repository still contains the `paypal-reader-for-woocommerce-m1-spike` discovery workspace for protocol research.
