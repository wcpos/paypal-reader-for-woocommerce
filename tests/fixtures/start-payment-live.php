<?php
/**
 * Calls AjaxHandler::start_payment in live mode for order 21, in its own PHP process.
 *
 * Usage: php start-payment-live.php <caller>
 *   key_only        order key + nonce, no cashier token, no logged-in user
 *   valid_token     plus a cashier payment token minted for user 7 on order 21
 *   forged_token    plus a cashier payment token with a broken signature
 *   other_order     plus a cashier payment token minted for order 22
 *   cookie_cashier  no cashier token; logged-in user 7, whom WCPOS lets edit order 21
 *   cookie_stranger no cashier token; logged-in user 9, whom WCPOS does not
 * Prints {"success":bool,"has_access_token":bool,"saved":bool} as JSON.
 */

declare(strict_types=1);

namespace {
    $GLOBALS['prwc_caller'] = (string) ($argv[1] ?? '');
    $GLOBALS['prwc_json'] = null;
    $GLOBALS['prwc_saved'] = false;

    class WP_Error {
        public function get_error_message(): string { return 'error'; }
    }
    class WP_User {
        public $ID;
        public function __construct(int $id) { $this->ID = $id; }
    }
    class PrwcStartOrder {
        private $id;
        public function __construct(int $id) { $this->id = $id; }
        public function get_id(): int { return $this->id; }
        public function get_order_key(): string { return 'wc_order_key' . $this->id; }
        public function get_total(): string { return '10.00'; }
        public function get_currency(): string { return 'USD'; }
        public function update_meta_data($key, $value): void {}
        public function add_order_note($note): void {}
        public function save(): void { $GLOBALS['prwc_saved'] = true; }
    }
    class PrwcStop extends \Exception {}

    function __($text, $domain = null) { return $text; }
    function add_action(...$args): void {}
    function absint($value): int { return abs((int) $value); }
    function check_ajax_referer(...$args) { return 1; }
    function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
    function sanitize_text_field($value): string { return trim((string) $value); }
    function current_user_can(...$args): bool { return false; }
    function get_current_user_id(): int {
        return ['cookie_cashier' => 7, 'cookie_stranger' => 9][$GLOBALS['prwc_caller']] ?? 0;
    }
    function get_user_by($field, $value) { return (int) $value > 0 ? new WP_User((int) $value) : false; }
    function is_wp_error($thing): bool { return $thing instanceof WP_Error; }
    function wp_salt($scheme = 'auth'): string { return 'fixture-salt-' . $scheme; }
    function wc_get_order($id) { return in_array((int) $id, [21, 22], true) ? new PrwcStartOrder((int) $id) : false; }
    function get_option($name, $default = false) {
        if ($name === 'woocommerce_paypal_reader_for_woocommerce_settings') {
            return ['enabled' => 'yes', 'test_mode' => 'no', 'client_id' => 'client-1', 'assertion' => 'assertion-1'];
        }
        return $default;
    }
    function get_transient($key) { return 'MERCHANT-SECRET-TOKEN'; }
    function set_transient(...$args): bool { return true; }
    function delete_transient(...$args): bool { return true; }
    function wp_json_encode($value) { return json_encode($value); }
    function wp_remote_request($url, $args = []) { return ['code' => 201, 'body' => json_encode(['location' => 'wss://reader.example.test/s/1', 'authorized' => []])]; }
    function wp_remote_post($url, $args = []) { return ['code' => 200, 'body' => json_encode(['access_token' => 'MERCHANT-SECRET-TOKEN', 'expires_in' => 7200])]; }
    function wp_remote_retrieve_response_code($response) { return $response['code']; }
    function wp_remote_retrieve_body($response) { return $response['body']; }
    function wp_generate_password($length = 12, $special = true, $extra = false): string { return str_repeat('a', $length); }
    function wp_send_json_success($data = null) { $GLOBALS['prwc_json'] = ['success' => true, 'data' => $data]; throw new PrwcStop(); }
    function wp_send_json_error($data = null) { $GLOBALS['prwc_json'] = ['success' => false, 'data' => $data]; throw new PrwcStop(); }
}

namespace WCPOS\WooCommercePOS\Services {
    class Auth {
        public static function instance(): self { return new self(); }
        public function validate_token($token = '', $token_type = 'access') { return new \WP_Error(); }
    }
    class Cashier {
        public static function instance(): self { return new self(); }
        public function can_open_pos(\WP_User $user): bool { return true; }
    }
    class Permission_Rules {
        public static function verdict(string $collection, string $context, int $object_id = 0, int $actor_id = 0, string $lane = 'v2', array $params = []) {
            return $collection === 'orders' && $context === 'edit' && $actor_id === 7;
        }
    }
}

namespace {
    require __DIR__ . '/../../paypal-reader-for-woocommerce.php';

    use WCPOS\WooCommercePOS\PayPalReader\AjaxHandler;
    use WCPOS\WooCommercePOS\PayPalReader\CashierGate;

    $_POST = [
        'action'    => 'paypal_reader_start_payment',
        'nonce'     => 'nonce-1',
        'order_id'  => '21',
        'order_key' => 'wc_order_key21',
        'reader_id' => 'link-1',
    ];
    $caller = $GLOBALS['prwc_caller'];
    if ($caller === 'valid_token') {
        $_POST['cashier_token'] = CashierGate::mint(new PrwcStartOrder(21), 7);
    } elseif ($caller === 'forged_token') {
        $token = CashierGate::mint(new PrwcStartOrder(21), 7);
        $_POST['cashier_token'] = substr($token, 0, -4) . 'beef';
    } elseif ($caller === 'other_order') {
        $_POST['cashier_token'] = CashierGate::mint(new PrwcStartOrder(22), 7);
    }

    try {
        (new AjaxHandler())->start_payment();
    } catch (PrwcStop $stop) {
    }

    $json = $GLOBALS['prwc_json'];
    echo json_encode([
        'success'          => is_array($json) && $json['success'] === true,
        'has_access_token' => strpos((string) json_encode($json), 'MERCHANT-SECRET-TOKEN') !== false,
        'saved'            => $GLOBALS['prwc_saved'],
    ]);
}
