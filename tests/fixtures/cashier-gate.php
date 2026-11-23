<?php
/**
 * Runs one CashierGate scenario in its own PHP process and prints the result as JSON.
 *
 * Usage: php cashier-gate.php <scenario>
 *
 * Stand-ins for the three WCPOS classes the gate borrows (Auth, Cashier, Permission_Rules)
 * are defined unless the scenario says they are absent. Their answers follow the scenario:
 * the JWT "jwt-cashier-7" names user 7; any other JWT is refused as WCPOS refuses it.
 */

declare(strict_types=1);

namespace {
    $GLOBALS['prwc_scenario'] = (string) ($argv[1] ?? '');
    $GLOBALS['prwc_logs'] = [];

    class WP_Error {}
    class WP_User {
        public $ID;
        public function __construct(int $id) { $this->ID = $id; }
    }
    class PrwcGateLogger {
        public function info($message, $context = []): void { $GLOBALS['prwc_logs'][] = (string) $message; }
        public function warning($message, $context = []): void { $GLOBALS['prwc_logs'][] = (string) $message; }
        public function error($message, $context = []): void { $GLOBALS['prwc_logs'][] = (string) $message; }
    }
    class PrwcGateOrder {
        private $id;
        private $key;
        public function __construct(int $id, string $key) { $this->id = $id; $this->key = $key; }
        public function get_id(): int { return $this->id; }
        public function get_order_key(): string { return $this->key; }
    }

    function wc_get_logger() { return new PrwcGateLogger(); }
    function is_wp_error($thing): bool { return $thing instanceof WP_Error; }
    function wp_salt($scheme = 'auth'): string { return 'fixture-salt-' . $scheme; }
    function get_user_by($field, $value) { return (int) $value > 0 ? new WP_User((int) $value) : false; }
}

namespace WCPOS\WooCommercePOS\Services {
    $prwc_absent = in_array($GLOBALS['prwc_scenario'], ['classes_absent', 'classes_absent_verify'], true);

    if (!$prwc_absent) {
        class Auth {
            public static function instance(): self { return new self(); }
            public function validate_token($token = '', $token_type = 'access') {
                if ($GLOBALS['prwc_scenario'] === 'auth_throws') {
                    throw new \RuntimeException('decoder blew up');
                }
                if ($GLOBALS['prwc_scenario'] === 'auth_wrong_type') {
                    return ['data' => ['user' => ['id' => 7]]];
                }
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
            public function can_open_pos(\WP_User $user): bool {
                return !in_array($GLOBALS['prwc_scenario'], ['no_pos_access', 'lost_pos_access'], true);
            }
        }
        class Permission_Rules {
            public static function verdict(string $collection, string $context, int $object_id = 0, int $actor_id = 0, string $lane = 'v2', array $params = []) {
                if ($GLOBALS['prwc_scenario'] === 'verdict_wrong_type') {
                    return 1;
                }
                if ($GLOBALS['prwc_scenario'] === 'no_order_edit') {
                    return new \WP_Error();
                }
                return $collection === 'orders' && $context === 'edit' && $object_id === 21 && $actor_id === 7;
            }
        }
    }
}

namespace {
    require __DIR__ . '/../../includes/Logger.php';
    require __DIR__ . '/../../includes/CashierGate.php';

    use WCPOS\WooCommercePOS\PayPalReader\CashierGate;

    $order = new PrwcGateOrder(21, 'wc_order_key21');
    $other = new PrwcGateOrder(22, 'wc_order_key22');
    $now = 1800000000;
    $result = [];

    switch ($GLOBALS['prwc_scenario']) {
        case 'valid':
            $user = CashierGate::user_from_wcpos_token('jwt-cashier-7', $order);
            $token = CashierGate::mint($order, 7, $now);
            $result = [
                'user'                 => $user,
                'token_is_string'      => is_string($token) && $token !== '',
                'verifies'             => CashierGate::verify($token, $order, $now),
                'verifies_near_expiry' => CashierGate::verify($token, $order, $now + CashierGate::TTL),
                'ttl'                  => CashierGate::TTL,
            ];
            break;
        case 'no_token':
            $result = ['user' => CashierGate::user_from_wcpos_token('', $order)];
            break;
        case 'forged_jwt':
            $result = ['user' => CashierGate::user_from_wcpos_token('jwt-forged', $order)];
            break;
        case 'expired':
            $token = CashierGate::mint($order, 7, $now);
            $result = ['verifies' => CashierGate::verify($token, $order, $now + CashierGate::TTL + 1)];
            break;
        case 'far_future':
            // A token whose expiry is further out than one TTL was not minted by this gate.
            $token = CashierGate::mint($order, 7, $now + 10 * CashierGate::TTL);
            $result = ['verifies' => CashierGate::verify($token, $order, $now)];
            break;
        case 'tampered':
            $token = CashierGate::mint($order, 7, $now);
            $parts = explode('.', $token);
            $user_swapped = '8' . substr($token, strlen($parts[0]));
            $last = substr($token, -1);
            $sig_flipped = substr($token, 0, -1) . ($last === 'a' ? 'b' : 'a');
            $result = [
                'user_swapped' => CashierGate::verify($user_swapped, $order, $now),
                'sig_flipped'  => CashierGate::verify($sig_flipped, $order, $now),
                'garbage'      => CashierGate::verify('not-a-token', $order, $now),
                'empty'        => CashierGate::verify('', $order, $now),
            ];
            break;
        case 'other_order':
            $token = CashierGate::mint($order, 7, $now);
            $rekeyed = new PrwcGateOrder(21, 'wc_order_NEWKEY');
            $result = [
                'other_order' => CashierGate::verify($token, $other, $now),
                'order_rekeyed' => CashierGate::verify($token, $rekeyed, $now),
            ];
            break;
        case 'no_pos_access':
        case 'no_order_edit':
        case 'verdict_wrong_type':
        case 'auth_wrong_type':
            $result = ['user' => CashierGate::user_from_wcpos_token('jwt-cashier-7', $order)];
            break;
        case 'auth_throws':
            $result = [
                'user' => CashierGate::user_from_wcpos_token('jwt-cashier-7', $order),
                'logs' => count($GLOBALS['prwc_logs']),
            ];
            break;
        case 'lost_pos_access':
            // Minted while the user could take payment; the handler re-checks the user now.
            $token = CashierGate::mint($order, 7, $now);
            $result = ['verifies' => CashierGate::verify($token, $order, $now)];
            break;
        case 'classes_absent':
            $result = [
                'user' => CashierGate::user_from_wcpos_token('jwt-cashier-7', $order),
                'may'  => CashierGate::user_may_take_payment(7, $order),
                'logs' => count($GLOBALS['prwc_logs']),
            ];
            break;
        case 'classes_absent_verify':
            $token = CashierGate::mint($order, 7, $now);
            $result = ['verifies' => CashierGate::verify($token, $order, $now)];
            break;
        case 'may_take_payment':
            $result = [
                'cashier'  => CashierGate::user_may_take_payment(7, $order),
                'stranger' => CashierGate::user_may_take_payment(9, $order),
                'nobody'   => CashierGate::user_may_take_payment(0, $order),
            ];
            break;
        default:
            fwrite(STDERR, "unknown scenario\n");
            exit(2);
    }

    echo json_encode($result);
}
