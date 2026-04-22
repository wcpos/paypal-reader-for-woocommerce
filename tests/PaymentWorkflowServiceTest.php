<?php

declare(strict_types=1);

require_once __DIR__ . '/../paypal-reader-for-woocommerce.php';

use WCPOS\WooCommercePOS\PayPalReader\Services\AttemptStore;
use WCPOS\WooCommercePOS\PayPalReader\Services\MockReaderService;
use WCPOS\WooCommercePOS\PayPalReader\Services\PaymentWorkflowService;

return [
    'starts a payment and persists the active attempt by order id' => function (): void {
        $store = new AttemptStore();
        $workflow = new PaymentWorkflowService(new MockReaderService(), $store);

        $started = $workflow->start_payment([
            'order_id' => 77,
            'amount' => 1444,
            'currency' => 'USD',
            'reader_id' => 'mock-reader-1',
        ]);

        assert_same('pending', $started['state']);
        assert_true($started['attempt_id'] !== '');
        assert_same($started['attempt_id'], $store->get_for_order(77)['attempt_id']);
    },
    'polls an active payment to completion and stores the final state' => function (): void {
        $store = new AttemptStore();
        $workflow = new PaymentWorkflowService(new MockReaderService(), $store);

        $started = $workflow->start_payment([
            'order_id' => 88,
            'amount' => 1444,
            'currency' => 'USD',
            'reader_id' => 'mock-reader-1',
        ]);

        $pending = $workflow->get_payment_status(88, 5);
        $final = $workflow->get_payment_status(88, 99);

        assert_same('AUTHORIZING', $pending['current_progress']);
        assert_same('completed', $final['state']);
        assert_same('COMPLETED', $final['result']['resultStatus']);
        assert_same('completed', $store->get_for_order(88)['state']);
    },
    'cancels an active payment when mock cancel behavior is cancellable' => function (): void {
        $store = new AttemptStore();
        $workflow = new PaymentWorkflowService(new MockReaderService([
            'mock_cancel_behavior' => 'canceled',
        ]), $store);

        $workflow->start_payment([
            'order_id' => 66,
            'amount' => 1444,
            'currency' => 'USD',
            'reader_id' => 'mock-reader-1',
        ]);

        $cancel = $workflow->cancel_payment(66);
        $status = $workflow->get_payment_status(66);

        assert_same('canceled', $cancel['state']);
        assert_same('canceled', $status['state']);
        assert_same('CANCELED', $status['result']['resultStatus']);
    },
    'returns an idempotent status payload when no attempt exists for the order' => function (): void {
        $workflow = new PaymentWorkflowService(new MockReaderService(), new AttemptStore());

        $status = $workflow->get_payment_status(999);

        assert_same('idle', $status['state']);
        assert_same(null, $status['attempt_id']);
    },
];
