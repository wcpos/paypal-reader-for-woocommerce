<?php

declare(strict_types=1);

require_once __DIR__ . '/../paypal-reader-for-woocommerce.php';

use WCPOS\WooCommercePOS\PayPalReader\Services\MockReaderService;

return [
    'lists one configured virtual reader by default' => function (): void {
        $service = new MockReaderService([
            'mock_reader_name' => 'Front Counter Reader',
        ]);

        $readers = $service->get_readers();

        assert_count_is(1, $readers);
        assert_same('Front Counter Reader', $readers[0]['label']);
        assert_same('PayPal Reader', $readers[0]['model']);
        assert_same('ready', $readers[0]['status']);
    },
    'creates a simulated payment transcript that completes after progress updates' => function (): void {
        $service = new MockReaderService();

        $attempt = $service->create_payment([
            'order_id' => 101,
            'amount' => 1444,
            'currency' => 'USD',
            'reader_id' => 'mock-reader-1',
        ]);

        assert_same('STARTING_TRANSACTION', $attempt['progress'][0]);
        assert_same('pending', $attempt['state']);

        $status = $service->advance_payment($attempt['attempt_id'], 5);
        $final  = $service->advance_payment($attempt['attempt_id'], 99);

        assert_same('AUTHORIZING', $status['current_progress']);
        assert_same('completed', $final['state']);
        assert_same('COMPLETED', $final['result']['resultStatus']);
        assert_same('1444', $final['result']['resultPayload']['amount']);
    },
    'can simulate a too-late cancel where completion remains the source of truth' => function (): void {
        $service = new MockReaderService([
            'mock_cancel_behavior' => 'too_late',
        ]);

        $attempt = $service->create_payment([
            'order_id' => 202,
            'amount' => 1444,
            'currency' => 'USD',
            'reader_id' => 'mock-reader-1',
        ]);

        $cancel = $service->cancel_payment($attempt['attempt_id']);
        $final  = $service->advance_payment($attempt['attempt_id'], 99);

        assert_same('too_late', $cancel['cancel_behavior']);
        assert_same('completed', $final['state']);
        assert_same('COMPLETED', $final['result']['resultStatus']);
    },
];
