<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader;

use WCPOS\WooCommercePOS\Services;
final class CashierGate {
    public const TTL = 900; // 15 minutes.

    public static function user_from_wcpos_token(string $jwt, $order): int {
        try {
            if ($jwt === '') { return 0; }
            $decoded = self::wcpos(static fn() => Services\Auth::instance()->validate_token($jwt), 'auth');
            if ($decoded instanceof \WP_Error) { return 0; }
            $id = $decoded->data->user->id ?? null;
            return is_scalar($id) && ($user_id = (int) $id) > 0 && self::user_may_take_payment($user_id, $order) ? $user_id : 0;
        } catch (\Throwable $error) { return 0; }
    }

    public static function user_may_take_payment(int $user_id, $order): bool {
        try {
            $user = get_user_by('id', $user_id);
            return $user instanceof \WP_User && self::wcpos(static fn() => Services\Cashier::instance()->can_open_pos($user), 'cashier') === true
                && self::wcpos(static fn() => Services\Permission_Rules::verdict('orders', 'edit', (int) $order->get_id(), $user_id), 'verdict') === true;
        } catch (\Throwable $error) { return false; }
    }

    public static function mint($order, int $user_id, ?int $now = null): string {
        try {
            $expires = ($now ?? time()) + self::TTL;
            return $user_id . '.' . $expires . '.' . hash_hmac('sha256', $order->get_id() . '|' . $order->get_order_key() . '|' . $user_id . '|' . $expires, wp_salt('auth'));
        } catch (\Throwable $error) { return ''; }
    }

    public static function verify(string $token, $order, ?int $now = null): bool {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) { return false; }
            [$user_id, $expires, $hmac] = $parts;
            $now = $now ?? time();
            return $now <= $expires && $expires <= $now + self::TTL
                && hash_equals(hash_hmac('sha256', $order->get_id() . '|' . $order->get_order_key() . '|' . $user_id . '|' . $expires, wp_salt('auth')), $hmac)
                && self::user_may_take_payment((int) $user_id, $order);
        } catch (\Throwable $error) { return false; }
    }

    private static function wcpos(callable $call, string $type) {
        try {
            $result = $call();
            if (($type === 'auth' && is_object($result)) || ($type !== 'auth' && is_bool($result))
                || ($type === 'verdict' && $result instanceof \WP_Error)) {
                return $result;
            }
        } catch (\Throwable $error) {}
        Logger::log('WCPOS cashier check failed.');
        return null;
    }
}
