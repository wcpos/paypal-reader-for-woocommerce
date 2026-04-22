<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader\Services;

class PaymentWorkflowService {
    private MockReaderService $reader_service;
    private AttemptStore $attempt_store;

    public function __construct(MockReaderService $reader_service, AttemptStore $attempt_store) {
        $this->reader_service = $reader_service;
        $this->attempt_store = $attempt_store;
    }

    public function start_payment(array $request): array {
        $attempt = $this->reader_service->create_payment($request);
        $this->attempt_store->save_for_order((int) $attempt['order_id'], $attempt);

        return $attempt;
    }

    public function get_payment_status(int $order_id, int $steps = 1): array {
        $attempt = $this->attempt_store->get_for_order($order_id);
        if (!$attempt) {
            return [
                'state' => 'idle',
                'attempt_id' => null,
            ];
        }

        if (in_array($attempt['state'], ['completed', 'canceled'], true)) {
            return $attempt;
        }

        $this->reader_service->remember_attempt($attempt);
        $updated = $this->reader_service->advance_payment((string) $attempt['attempt_id'], $steps);
        $this->attempt_store->save_for_order($order_id, $updated);

        return $updated;
    }

    public function cancel_payment(int $order_id): array {
        $attempt = $this->attempt_store->get_for_order($order_id);
        if (!$attempt) {
            return [
                'state' => 'idle',
                'attempt_id' => null,
                'cancel_behavior' => 'none',
            ];
        }

        $this->reader_service->remember_attempt($attempt);
        $result = $this->reader_service->cancel_payment((string) $attempt['attempt_id']);
        $updated = $this->reader_service->get_attempt((string) $attempt['attempt_id']) ?? $attempt;
        $this->attempt_store->save_for_order($order_id, $updated);

        return $result;
    }
}
