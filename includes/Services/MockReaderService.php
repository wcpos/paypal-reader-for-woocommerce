<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader\Services;

use WCPOS\WooCommercePOS\PayPalReader\Settings;

class MockReaderService {
    private array $settings;

    /**
     * @var array<string,array<string,mixed>>
     */
    private array $attempts = [];

    public function __construct(array $settings = []) {
        $this->settings = $settings;
    }


    public function remember_attempt(array $attempt): void {
        $attempt_id = (string) ($attempt['attempt_id'] ?? '');
        if ($attempt_id === '') {
            throw new \RuntimeException('Mock attempt is missing attempt_id.');
        }

        $this->attempts[$attempt_id] = $attempt;
    }

    public function get_attempt(string $attempt_id): ?array {
        return $this->attempts[$attempt_id] ?? null;
    }

    public function get_readers(): array {
        return [
            [
                'id' => 'mock-reader-1',
                'label' => Settings::get_mock_reader_name($this->settings),
                'model' => 'PayPal Reader',
                'serial_number' => '3321900308',
                'status' => 'ready',
            ],
        ];
    }

    public function create_payment(array $request): array {
        $attempt_id = 'attempt-' . substr(md5(json_encode($request) . microtime(true)), 0, 12);
        $progress = [
            'STARTING_TRANSACTION',
            'PREPARING',
            'INITIALIZING',
            'PRESENT_CARD',
            'CARD_PRESENTED',
            'AUTHORIZING',
            'APPROVED',
            'COMPLETED',
        ];

        $attempt = [
            'attempt_id' => $attempt_id,
            'order_id' => (int) ($request['order_id'] ?? 0),
            'amount' => (int) ($request['amount'] ?? 0),
            'currency' => (string) ($request['currency'] ?? 'USD'),
            'reader_id' => (string) ($request['reader_id'] ?? 'mock-reader-1'),
            'state' => 'pending',
            'progress' => $progress,
            'progress_index' => 0,
            'cancel_behavior' => Settings::get_mock_cancel_behavior($this->settings),
            'result' => null,
        ];

        $this->attempts[$attempt_id] = $attempt;

        return $attempt;
    }

    public function advance_payment(string $attempt_id, int $steps = 1): array {
        $attempt = $this->attempts[$attempt_id] ?? null;
        if (!$attempt) {
            throw new \RuntimeException('Unknown mock payment attempt: ' . $attempt_id);
        }

        $target_index = min(
            count($attempt['progress']) - 1,
            $attempt['progress_index'] + max(1, $steps)
        );
        $attempt['progress_index'] = $target_index;
        $attempt['current_progress'] = $attempt['progress'][$target_index];

        if ($target_index >= count($attempt['progress']) - 1) {
            if ($attempt['state'] !== 'canceled') {
                $attempt['state'] = 'completed';
                $attempt['result'] = [
                    'type' => 'PAYMENT_RESULT_RESPONSE',
                    'internalTraceId' => $attempt['attempt_id'],
                    'resultStatus' => 'COMPLETED',
                    'resultPayload' => [
                        'amount' => (string) $attempt['amount'],
                        'currency' => $attempt['currency'],
                        'gratuityAmount' => '0',
                        'reference' => 'WCPOS-MOCK-ORDER-' . $attempt['order_id'],
                        'trackingId' => 'mock-tracking-id',
                        'checkoutUUID' => 'mock-checkout-uuid',
                        'CARD_PAYMENT_UUID' => 'mock-card-payment-uuid',
                    ],
                ];
            }
        }

        $this->attempts[$attempt_id] = $attempt;

        return $attempt;
    }

    public function cancel_payment(string $attempt_id): array {
        $attempt = $this->attempts[$attempt_id] ?? null;
        if (!$attempt) {
            throw new \RuntimeException('Unknown mock payment attempt: ' . $attempt_id);
        }

        if ($attempt['cancel_behavior'] === 'canceled') {
            $attempt['state'] = 'canceled';
            $attempt['result'] = [
                'type' => 'PAYMENT_RESULT_RESPONSE',
                'internalTraceId' => $attempt['attempt_id'],
                'resultStatus' => 'CANCELED',
                'resultErrorMessage' => 'Canceled by merchant request',
            ];
        }

        $this->attempts[$attempt_id] = $attempt;

        return [
            'attempt_id' => $attempt['attempt_id'],
            'cancel_behavior' => $attempt['cancel_behavior'],
            'state' => $attempt['state'],
        ];
    }
}
