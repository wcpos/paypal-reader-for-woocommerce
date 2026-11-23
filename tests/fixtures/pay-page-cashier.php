<?php
/**
 * Renders the order-pay script data for order 21 in its own PHP process.
 *
 * Usage: php pay-page-cashier.php <wcpos|storefront> <query arg name> <jwt>
 * "wcpos" means the page renders inside the WCPOS pay template (wcpos_before_pay has fired).
 * The WCPOS stand-ins accept only the JWT "jwt-cashier-7" (user 7, who may edit order 21).
 * Prints the localized paypalReaderData as JSON, or "null" when none was printed.
 */

declare(strict_types=1);

namespace {
    $GLOBALS['prwc_localized'] = null;
    $GLOBALS['prwc_in_wcpos'] = ($argv[1] ?? '') === 'wcpos';
    $_GET['key'] = 'wc_order_key21';
    if (($argv[2] ?? '') !== '') {
        $_GET[$argv[2]] = (string) ($argv[3] ?? '');
    }

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
    class WP_Error {}
    class WP_User {
        public $ID;
        public function __construct(int $id) { $this->ID = $id; }
    }
    class PrwcCashierOrder {
        public function get_id(): int { return 21; }
        public function get_order_key(): string { return 'wc_order_key21'; }
        public function get_total(): string { return '10.00'; }
        public function get_currency(): string { return 'USD'; }
        public function is_paid(): bool { return false; }
        public function needs_payment(): bool { return true; }
    }

    function __($text, $domain = null) { return $text; }
    function add_action(...$args): void {}
    function add_filter(...$args): void {}
    function did_action($hook): int { return $hook === 'wcpos_before_pay' && $GLOBALS['prwc_in_wcpos'] ? 1 : 0; }
    function get_option($name, $default = false) { return $default; }
    function get_query_var($var, $default = '') { return $var === 'order-pay' ? 21 : $default; }
    function wc_get_order($id) { return (int) $id === 21 ? new PrwcCashierOrder() : false; }
    function wp_enqueue_script(...$args): void {}
    function wp_enqueue_style(...$args): void {}
    function wp_localize_script($handle, $name, $data): void { $GLOBALS['prwc_localized'] = $data; }
    function admin_url($path = ''): string { return 'http://example.test/wp-admin/' . $path; }
    function wp_create_nonce($action = -1): string { return 'nonce-' . $action; }
    function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
    function sanitize_text_field($value): string { return trim((string) $value); }
    function current_user_can(...$args): bool { return false; }
    function is_wp_error($thing): bool { return $thing instanceof WP_Error; }
    function wp_salt($scheme = 'auth'): string { return 'fixture-salt-' . $scheme; }
    function get_user_by($field, $value) { return (int) $value > 0 ? new WP_User((int) $value) : false; }
}

namespace WCPOS\WooCommercePOS\Services {
    class Auth {
        public static function instance(): self { return new self(); }
        public function validate_token($token = '', $token_type = 'access') {
            if ($token !== 'jwt-cashier-7') {
                return new \WP_Error();
            }
            $decoded = new \stdClass();
            $decoded->data = new \stdClass();
            $decoded->data->user = new \stdClass();
            $decoded->data->user->id = 7;
            return $decoded;
        }
    }
    class Cashier {
        public static function instance(): self { return new self(); }
        public function can_open_pos(\WP_User $user): bool { return true; }
    }
    class Permission_Rules {
        public static function verdict(string $collection, string $context, int $object_id = 0, int $actor_id = 0, string $lane = 'v2', array $params = []) {
            return $collection === 'orders' && $context === 'edit' && $object_id === 21 && $actor_id === 7;
        }
    }
}

namespace {
    require __DIR__ . '/../../paypal-reader-for-woocommerce.php';

    $gateway = new \WCPOS\WooCommercePOS\PayPalReader\Gateway();
    $gateway->enqueue_payment_scripts();

    echo json_encode($GLOBALS['prwc_localized']);
}
