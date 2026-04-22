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

    /**
     * Returns the active mode: 'mock', 'test', or 'live'.
     *
     * - 'mock' is a CI/CD-only path, activated via the PRWC_USE_MOCK_READER
     *   constant or the paypal_reader_for_woocommerce_use_mock_reader filter.
     *   It is never surfaced in the merchant-facing admin UI.
     * - 'test' is the standard merchant test mode (test_mode checkbox on, or
     *   checkbox off but no Zettle credentials configured yet).
     * - 'live' requires the test_mode checkbox to be off AND Zettle
     *   credentials to be present.
     */
    public static function get_mode(?array $settings = null): string {
        if (self::use_mock_reader()) {
            return 'mock';
        }

        $settings = $settings ?? self::get_gateway_settings();

        if (self::is_test_mode($settings)) {
            return 'test';
        }

        $client_id = trim((string) ($settings['client_id'] ?? ''));
        $assertion = trim((string) ($settings['assertion'] ?? ''));

        if ($client_id !== '' && $assertion !== '') {
            return 'live';
        }

        return 'test';
    }

    public static function is_test_mode(?array $settings = null): bool {
        $settings = $settings ?? self::get_gateway_settings();
        $value = $settings['test_mode'] ?? 'yes';

        return $value === 'yes' || $value === true || $value === 1 || $value === '1';
    }

    public static function use_mock_reader(): bool {
        $enabled = defined('PRWC_USE_MOCK_READER') && constant('PRWC_USE_MOCK_READER');

        if (function_exists('apply_filters')) {
            $enabled = (bool) apply_filters('paypal_reader_for_woocommerce_use_mock_reader', $enabled);
        }

        return (bool) $enabled;
    }

    public static function get_mock_reader_name(?array $settings = null): string {
        $name = 'WCPOS Mock Reader';

        if ($settings !== null) {
            $override = trim((string) ($settings['mock_reader_name'] ?? ''));
            if ($override !== '') {
                $name = $override;
            }
        }

        if (defined('PRWC_MOCK_READER_NAME')) {
            $override = trim((string) constant('PRWC_MOCK_READER_NAME'));
            if ($override !== '') {
                $name = $override;
            }
        }

        if (function_exists('apply_filters')) {
            $filtered = (string) apply_filters('paypal_reader_for_woocommerce_mock_reader_name', $name);
            if ($filtered !== '') {
                $name = $filtered;
            }
        }

        return $name;
    }

    public static function get_mock_cancel_behavior(?array $settings = null): string {
        $behavior = 'canceled';

        if ($settings !== null && isset($settings['mock_cancel_behavior'])) {
            $behavior = (string) $settings['mock_cancel_behavior'];
        }

        if (defined('PRWC_MOCK_CANCEL_BEHAVIOR')) {
            $behavior = (string) constant('PRWC_MOCK_CANCEL_BEHAVIOR');
        }

        if (function_exists('apply_filters')) {
            $behavior = (string) apply_filters('paypal_reader_for_woocommerce_mock_cancel_behavior', $behavior);
        }

        return in_array($behavior, ['canceled', 'too_late'], true) ? $behavior : 'canceled';
    }
}
