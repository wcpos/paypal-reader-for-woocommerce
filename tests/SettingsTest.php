<?php

declare(strict_types=1);

require_once __DIR__ . '/../paypal-reader-for-woocommerce.php';

use WCPOS\WooCommercePOS\PayPalReader\Settings;

return [
    'defaults to test mode when no credentials exist' => function (): void {
        assert_same('test', Settings::get_mode([
            'test_mode' => 'yes',
            'client_id' => '',
            'assertion' => '',
        ]));
    },
    'stays in test mode when test_mode is on even with credentials' => function (): void {
        assert_same('test', Settings::get_mode([
            'test_mode' => 'yes',
            'client_id' => 'client-123',
            'assertion' => 'assertion-456',
        ]));
    },
    'selects live mode only when test_mode is off and credentials exist' => function (): void {
        assert_same('live', Settings::get_mode([
            'test_mode' => 'no',
            'client_id' => 'client-123',
            'assertion' => 'assertion-456',
        ]));
    },
    'falls back to test mode when test_mode is off but credentials are missing' => function (): void {
        assert_same('test', Settings::get_mode([
            'test_mode' => 'no',
            'client_id' => '',
            'assertion' => '',
        ]));
    },
    'test_mode checkbox defaults to enabled when unset' => function (): void {
        assert_true(Settings::is_test_mode([]));
    },
    'test_mode checkbox respects an explicit no' => function (): void {
        assert_true(!Settings::is_test_mode(['test_mode' => 'no']));
    },
    'falls back to a default mock reader name' => function (): void {
        assert_same('WCPOS Mock Reader', Settings::get_mock_reader_name());
    },
];
