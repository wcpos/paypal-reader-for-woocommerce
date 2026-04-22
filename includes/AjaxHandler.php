<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader;

use WCPOS\WooCommercePOS\PayPalReader\Services\AttemptStore;
use WCPOS\WooCommercePOS\PayPalReader\Services\MockReaderService;
use WCPOS\WooCommercePOS\PayPalReader\Services\PaymentWorkflowService;

class AjaxHandler {
    public function __construct() {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('wp_ajax_paypal_reader_get_readers', [$this, 'get_readers']);
        add_action('wp_ajax_nopriv_paypal_reader_get_readers', [$this, 'get_readers']);
        add_action('wp_ajax_paypal_reader_start_payment', [$this, 'start_payment']);
        add_action('wp_ajax_nopriv_paypal_reader_start_payment', [$this, 'start_payment']);
        add_action('wp_ajax_paypal_reader_check_payment_status', [$this, 'check_payment_status']);
        add_action('wp_ajax_nopriv_paypal_reader_check_payment_status', [$this, 'check_payment_status']);
        add_action('wp_ajax_paypal_reader_cancel_payment', [$this, 'cancel_payment']);
        add_action('wp_ajax_nopriv_paypal_reader_cancel_payment', [$this, 'cancel_payment']);
    }

    public function get_readers(): void {
        if (!$this->verify_nonce()) {
            return;
        }

        wp_send_json_success([
            'mode' => Settings::get_mode(),
            'readers' => $this->get_reader_service()->get_readers(),
        ]);
    }

    public function start_payment(): void {
        if (!$this->verify_nonce()) {
            return;
        }

        $order = $this->get_order_from_request();
        if (!$order || !$this->can_access_order($order)) {
            wp_send_json_error(__('Invalid order access.', 'paypal-reader-for-woocommerce'));
            return;
        }

        $reader_id = isset($_POST['reader_id']) ? sanitize_text_field(wp_unslash($_POST['reader_id'])) : 'mock-reader-1';
        $amount = isset($_POST['amount']) ? absint($_POST['amount']) : (int) round(((float) $order->get_total()) * 100);

        $attempt = $this->get_workflow_service()->start_payment([
            'order_id' => $order->get_id(),
            'amount' => $amount,
            'currency' => $order->get_currency(),
            'reader_id' => $reader_id,
        ]);

        $order->update_meta_data('_prwc_attempt_id', $attempt['attempt_id']);
        $order->update_meta_data('_prwc_reader_id', $reader_id);
        $order->update_meta_data('_prwc_payment_state', 'pending');
        $order->save();

        $order->add_order_note(sprintf('PayPal Reader: started mock payment attempt %s on %s', $attempt['attempt_id'], $reader_id));

        wp_send_json_success($attempt);
    }

    public function check_payment_status(): void {
        if (!$this->verify_nonce()) {
            return;
        }

        $order = $this->get_order_from_request();
        if (!$order || !$this->can_access_order($order)) {
            wp_send_json_error(__('Invalid order access.', 'paypal-reader-for-woocommerce'));
            return;
        }

        $status = $this->get_workflow_service()->get_payment_status($order->get_id(), 1);
        $state = (string) ($status['state'] ?? 'idle');

        if ($state === 'completed') {
            $result = $status['result'] ?? [];
            $transaction_id = (string) ($result['resultPayload']['CARD_PAYMENT_UUID'] ?? '');
            $order->update_meta_data('_prwc_payment_state', 'completed');
            $order->update_meta_data('_prwc_card_payment_uuid', $transaction_id);
            $order->update_meta_data('_prwc_tracking_id', (string) ($result['resultPayload']['trackingId'] ?? ''));
            $order->save();
        } elseif ($state === 'canceled') {
            $order->update_meta_data('_prwc_payment_state', 'canceled');
            $order->save();
        }

        wp_send_json_success($status);
    }

    public function cancel_payment(): void {
        if (!$this->verify_nonce()) {
            return;
        }

        $order = $this->get_order_from_request();
        if (!$order || !$this->can_access_order($order)) {
            wp_send_json_error(__('Invalid order access.', 'paypal-reader-for-woocommerce'));
            return;
        }

        $result = $this->get_workflow_service()->cancel_payment($order->get_id());
        if (($result['state'] ?? '') === 'canceled') {
            $order->update_meta_data('_prwc_payment_state', 'canceled');
            $order->save();
        }

        wp_send_json_success($result);
    }

    private function get_workflow_service(): PaymentWorkflowService {
        return new PaymentWorkflowService($this->get_reader_service(), new AttemptStore());
    }

    private function get_reader_service(): MockReaderService {
        $settings = Settings::get_gateway_settings();

        if (Settings::get_mode($settings) === 'live') {
            Logger::log('Live mode selected without a real reader implementation; falling back to mock reader service.');
        }

        return new MockReaderService($settings);
    }

    private function verify_nonce(): bool {
        if (!function_exists('check_ajax_referer')) {
            return true;
        }

        if (false === check_ajax_referer('paypal_reader_nonce', 'nonce', false)) {
            wp_send_json_error(__('Invalid request.', 'paypal-reader-for-woocommerce'));
            return false;
        }

        return true;
    }

    private function get_order_from_request() {
        if (!function_exists('wc_get_order')) {
            return null;
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if ($order_id <= 0) {
            return null;
        }

        return wc_get_order($order_id);
    }

    private function can_access_order($order): bool {
        if (function_exists('current_user_can') && current_user_can('manage_woocommerce')) {
            return true;
        }

        $provided_order_key = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';
        return $provided_order_key !== '' && $provided_order_key === $order->get_order_key();
    }
}
