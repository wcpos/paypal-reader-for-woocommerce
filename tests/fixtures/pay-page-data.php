<?php
/**
 * Renders the order-pay script data for one request, in its own PHP process.
 *
 * Usage: php pay-page-data.php <order-pay id> [<key query arg>]
 * Prints the localized paypalReaderData as JSON, or "null" when none was printed.
 * Order 14 exists with key wc_order_real14; any other id has no order.
 */

declare(strict_types=1);

class WC_Payment_Gateway {
    public $id = '';
    public $form_fields = [];
    public $method_title = '';
    public $method_description = '';
    public $has_fields = false;
    public $title = '';
    public $description = '';
    public function init_settings(): void {}
    public function get_option($key, $default = null) { return $default; }
    public function get_return_url($order = null): string { return ''; }
}

class PrwcFixtureOrder {
    public function get_id(): int { return 14; }
    public function get_order_key(): string { return 'wc_order_real14'; }
    public function get_total(): string { return '10.00'; }
    public function get_currency(): string { return 'USD'; }
    public function is_paid(): bool { return false; }
    public function needs_payment(): bool { return true; }
}

$GLOBALS['prwc_localized'] = null;
$GLOBALS['prwc_order_pay'] = (int) ($argv[1] ?? 0);
if (isset($argv[2])) {
    $_GET['key'] = $argv[2];
}

function __($text, $domain = null) { return $text; }
function add_action(...$args): void {}
function add_filter(...$args): void {}
function get_option($name, $default = false) { return $default; }
function get_query_var($var, $default = '') { return $var === 'order-pay' ? $GLOBALS['prwc_order_pay'] : $default; }
function wc_get_order($id) { return (int) $id === 14 ? new PrwcFixtureOrder() : false; }
function wp_enqueue_script(...$args): void {}
function wp_enqueue_style(...$args): void {}
function wp_localize_script($handle, $name, $data): void { $GLOBALS['prwc_localized'] = $data; }
function admin_url($path = ''): string { return 'http://example.test/wp-admin/' . $path; }
function wp_create_nonce($action = -1): string { return 'nonce-' . $action; }
function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
function sanitize_text_field($value): string { return trim((string) $value); }
function current_user_can(...$args): bool { return false; }

require __DIR__ . '/../../paypal-reader-for-woocommerce.php';

$gateway = new \WCPOS\WooCommercePOS\PayPalReader\Gateway();
$gateway->enqueue_payment_scripts();

echo json_encode($GLOBALS['prwc_localized']);
