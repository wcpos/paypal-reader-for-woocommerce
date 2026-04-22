<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader;

class Settings {
    public static function get_gateway_settings(): array {
        if (function_exists('get_option')) {
            $settings = get_option('woocommerce_paypal_reader_for_woocommerce_settings', []);
            return is_array($settings) ? $settings : [];
        }

        return [];
    }

    public static function get_mode(?array $settings = null): string {
        $settings = $settings ?? self::get_gateway_settings();
        $mode = (string) ($settings['mode'] ?? 'mock');
        $client_id = trim((string) ($settings['client_id'] ?? ''));
        $assertion = trim((string) ($settings['assertion'] ?? ''));

        if ($mode === 'live' && $client_id !== '' && $assertion !== '') {
            return 'live';
        }

        return 'mock';
    }

    public static function get_mock_reader_name(?array $settings = null): string {
        $settings = $settings ?? self::get_gateway_settings();
        $name = trim((string) ($settings['mock_reader_name'] ?? ''));

        return $name !== '' ? $name : 'WCPOS Mock Reader';
    }

    public static function get_mock_cancel_behavior(?array $settings = null): string {
        $settings = $settings ?? self::get_gateway_settings();
        $behavior = (string) ($settings['mock_cancel_behavior'] ?? 'canceled');

        return in_array($behavior, ['canceled', 'too_late'], true) ? $behavior : 'canceled';
    }
}
