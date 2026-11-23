<?php
/**
 * Calls AjaxHandler::confirm_payment or cancel_payment in live mode for order 21, in its own PHP process.
 * Usage: php confirm-payment-live.php <caller>
 * Prints {"success":bool,"saved":bool,"completed":bool,"canceled":bool} as JSON.
 */

declare(strict_types=1);

namespace {
    $GLOBALS['prwc_caller'] = (string) ($argv[1] ?? '');
    $GLOBALS['prwc_json'] = null;
    $GLOBALS['prwc_saved'] = false;
    $GLOBALS['prwc_meta_updates'] = [];

    class WP_Error {
        public function get_error_message(): string { return 'error'; }
    }
    class WP_User {
        public $ID;
        public function __construct(int $id) { $this->ID = $id; }
    }
    class PrwcConfirmOrder {
        private $id;
        private $meta = [
            '_prwc_payment_state' => 'pending',
            '_prwc_internal_trace_id' => 'prwc-21-TRACE',
            '_prwc_started_amount' => '1000',
        ];
        public function __construct(int $id) {
            $this->id = $id;
            if ($GLOBALS['prwc_caller'] === 'no_start') {
                $this->meta['_prwc_payment_state'] = '';
                unset($this->meta['_prwc_internal_trace_id']);
            } elseif ($GLOBALS['prwc_caller'] === 'replayed') {
                $this->meta['_prwc_payment_state'] = 'completed';
            } elseif ($GLOBALS['prwc_caller'] === 'no_trace_both') {
                unset($this->meta['_prwc_internal_trace_id']);
            }
        }
        public function get_id(): int { return $this->id; }
        public function get_order_key(): string { return 'wc_order_key' . $this->id; }
        public function get_total(): string { return $GLOBALS['prwc_caller'] === 'total_changed' ? '12.00' : '10.00'; }
        public function is_paid(): bool { return $GLOBALS['prwc_caller'] === 'paid'; }
        public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
        public function update_meta_data($key, $value): void {
            $GLOBALS['prwc_meta_updates'][] = [$key, $value];
            $this->meta[$key] = $value;
        }
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
    function wc_get_order($id) { return in_array((int) $id, [21, 22], true) ? new PrwcConfirmOrder((int) $id) : false; }
    function get_option($name, $default = false) {
        return $name === 'woocommerce_paypal_reader_for_woocommerce_settings' ? ['enabled' => 'yes', 'test_mode' => 'no'] : $default;
    }
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

    $caller = $GLOBALS['prwc_caller'];
    $_POST = [
        'nonce' => 'nonce-1',
        'order_id' => '21',
        'order_key' => 'wc_order_key21',
        'internal_trace_id' => 'prwc-21-TRACE',
        'result' => json_encode([
            'resultStatus' => $caller === 'lowercase_completed' ? 'completed' : 'COMPLETED',
            'resultPayload' => ['amount' => $caller === 'total_changed' ? 1200 : ($caller === 'reported_mismatch' ? 900 : 1000), 'CARD_PAYMENT_UUID' => 'u-1'],
        ]),
    ];
    if (!in_array($caller, ['key_only', 'cookie_cashier', 'cancel_key_only'], true)) {
        $_POST['cashier_token'] = CashierGate::mint(
            new PrwcConfirmOrder($caller === 'other_order_token' ? 22 : 21),
            7,
            $caller === 'expired_token' ? time() - 1000 : null
        );
    }
    if ($caller === 'wrong_trace') {
        $_POST['internal_trace_id'] = 'prwc-21-OTHER';
    } elseif ($caller === 'no_trace_posted') {
        unset($_POST['internal_trace_id']);
    } elseif ($caller === 'no_trace_both') {
        $_POST['internal_trace_id'] = '';
    }

    try {
        if (strpos($caller, 'cancel_') === 0) {
            (new AjaxHandler())->cancel_payment();
        } else {
            (new AjaxHandler())->confirm_payment();
        }
    } catch (PrwcStop $stop) {
    }

    $json = $GLOBALS['prwc_json'];
    echo json_encode([
        'success' => is_array($json) && $json['success'] === true,
        'saved' => $GLOBALS['prwc_saved'],
        'completed' => in_array(['_prwc_payment_state', 'completed'], $GLOBALS['prwc_meta_updates'], true),
        'canceled' => in_array(['_prwc_payment_state', 'canceled'], $GLOBALS['prwc_meta_updates'], true),
    ]);
}
