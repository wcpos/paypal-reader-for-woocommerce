<?php

declare(strict_types=1);

require_once __DIR__ . '/../paypal-reader-for-woocommerce.php';

use WCPOS\WooCommercePOS\PayPalReader\Settings;

return [
    'defaults to mock mode when no live credentials exist' => function (): void {
        assert_same('mock', Settings::get_mode([
            'mode' => '',
            'client_id' => '',
            'assertion' => '',
        ]));
    },
    'prefers live mode only when explicitly selected and credentials exist' => function (): void {
        assert_same('live', Settings::get_mode([
            'mode' => 'live',
            'client_id' => 'client-123',
            'assertion' => 'assertion-456',
        ]));
    },
    'falls back to a default mock reader name' => function (): void {
        assert_same('WCPOS Mock Reader', Settings::get_mock_reader_name([
            'mock_reader_name' => '',
        ]));
    },
];
