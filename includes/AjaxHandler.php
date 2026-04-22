<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader;

use WCPOS\WooCommercePOS\PayPalReader\Services\AttemptStore;
use WCPOS\WooCommercePOS\PayPalReader\Services\MockReaderService;
use WCPOS\WooCommercePOS\PayPalReader\Services\PaymentWorkflowService;
use WCPOS\WooCommercePOS\PayPalReader\Services\ReaderLinksService;
use WCPOS\WooCommercePOS\PayPalReader\Services\ReaderSessionService;
use WCPOS\WooCommercePOS\PayPalReader\Services\ZettleApiClient;

class AjaxHandler {
    public function __construct() {
        if (!function_exists('add_action')) {
            return;
        }

        // Checkout-side endpoints (available to guest customers on the
        // order-pay page with a valid order_key).
        add_action('wp_ajax_paypal_reader_get_readers',           [$this, 'get_readers']);
        add_action('wp_ajax_nopriv_paypal_reader_get_readers',    [$this, 'get_readers']);
        add_action('wp_ajax_paypal_reader_start_payment',         [$this, 'start_payment']);
        add_action('wp_ajax_nopriv_paypal_reader_start_payment',  [$this, 'start_payment']);
        add_action('wp_ajax_paypal_reader_confirm_payment',       [$this, 'confirm_payment']);
        add_action('wp_ajax_nopriv_paypal_reader_confirm_payment',[$this, 'confirm_payment']);
        add_action('wp_ajax_paypal_reader_cancel_payment',        [$this, 'cancel_payment']);
        add_action('wp_ajax_nopriv_paypal_reader_cancel_payment', [$this, 'cancel_payment']);
        add_action('wp_ajax_paypal_reader_check_payment_status',  [$this, 'check_payment_status']);
        add_action('wp_ajax_nopriv_paypal_reader_check_payment_status', [$this, 'check_payment_status']);

        // Admin-only endpoints (reader pairing, lives on the gateway
        // settings screen).
        add_action('wp_ajax_paypal_reader_pair_reader',   [$this, 'pair_reader']);
        add_action('wp_ajax_paypal_reader_unpair_reader', [$this, 'unpair_reader']);
    }

    public function get_readers(): void {
        if (!$this->verify_nonce('paypal_reader_nonce')) {
            return;
        }

        $mode = Settings::get_mode();

        if ($mode === 'mock') {
            $service = new MockReaderService(Settings::get_gateway_settings());
            wp_send_json_success([
                'mode'      => 'mock',
                'transport' => 'mock',
                'readers'   => $service->get_readers(),
            ]);
            return;
        }

        try {
            $links = $this->reader_links_service()->list_links();
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'mode'    => $mode,
            ]);
            return;
        }

        $readers = array_map(static function (array $link): array {
            return [
                'id'     => $link['linkId'],
                'linkId' => $link['linkId'],
                'label'  => $link['label'],
                'status' => 'ready',
            ];
        }, $links);

        wp_send_json_success([
            'mode'      => $mode,
            'transport' => 'zettle-ws',
            'readers'   => $readers,
        ]);
    }

    public function start_payment(): void {
        if (!$this->verify_nonce('paypal_reader_nonce')) {
            return;
        }

        $order = $this->get_order_from_request();
        if (!$order || !$this->can_access_order($order)) {
            wp_send_json_error(__('Invalid order access.', 'paypal-reader-for-woocommerce'));
            return;
        }

        $reader_id = isset($_POST['reader_id']) ? sanitize_text_field(wp_unslash($_POST['reader_id'])) : '';
        $amount = (int) round(((float) $order->get_total()) * 100);
        $currency = $order->get_currency();
        $mode = Settings::get_mode();

        if ($mode === 'mock') {
            $attempt = $this->mock_workflow_service()->start_payment([
                'order_id'  => $order->get_id(),
                'amount'    => $amount,
                'currency'  => $currency,
                'reader_id' => $reader_id !== '' ? $reader_id : 'mock-reader-1',
            ]);

            $order->update_meta_data('_prwc_attempt_id', $attempt['attempt_id']);
            $order->update_meta_data('_prwc_reader_id', $reader_id);
            $order->update_meta_data('_prwc_payment_state', 'pending');
            $order->save();
            $order->add_order_note(sprintf('PayPal Reader: started mock payment attempt %s on %s', $attempt['attempt_id'], $reader_id));

            wp_send_json_success(array_merge($attempt, ['transport' => 'mock']));
            return;
        }

        if ($reader_id === '') {
            wp_send_json_error(__('Select a reader before starting the payment.', 'paypal-reader-for-woocommerce'));
            return;
        }

        try {
            $channel_id = 'prwc-' . $order->get_id() . '-' . wp_generate_password(6, false, false);
            $session = $this->reader_session_service()->open_session($reader_id, $channel_id);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
            return;
        }

        $internal_trace_id = 'prwc-' . $order->get_id() . '-' . wp_generate_password(12, false, false);

        $order->update_meta_data('_prwc_reader_id', $reader_id);
        $order->update_meta_data('_prwc_internal_trace_id', $internal_trace_id);
        $order->update_meta_data('_prwc_payment_state', 'pending');
        $order->save();
        $order->add_order_note(sprintf('PayPal Reader: started live payment on reader %s (trace %s)', $reader_id, $internal_trace_id));

        wp_send_json_success([
            'transport'       => 'zettle-ws',
            'wsUrl'           => $session['location'],
            'linkId'          => $reader_id,
            'channelId'       => $channel_id,
            'accessToken'     => $session['accessToken'],
            'expiresAt'       => $session['expiresAt'],
            'internalTraceId' => $internal_trace_id,
            'amount'          => $amount,
            'currency'        => $currency,
            'tippingType'     => 'NONE',
        ]);
    }

    public function confirm_payment(): void {
        if (!$this->verify_nonce('paypal_reader_nonce')) {
            return;
        }

        $order = $this->get_order_from_request();
        if (!$order || !$this->can_access_order($order)) {
            wp_send_json_error(__('Invalid order access.', 'paypal-reader-for-woocommerce'));
            return;
        }

        $raw = isset($_POST['result']) ? wp_unslash($_POST['result']) : '';
        if (!is_string($raw) || $raw === '') {
            wp_send_json_error(__('Missing payment result payload.', 'paypal-reader-for-woocommerce'));
            return;
        }
        $result = json_decode($raw, true);
        if (!is_array($result)) {
            wp_send_json_error(__('Payment result payload was not valid JSON.', 'paypal-reader-for-woocommerce'));
            return;
        }

        $status = (string) ($result['resultStatus'] ?? '');
        $payload = is_array($result['resultPayload'] ?? null) ? $result['resultPayload'] : [];

        if ($status !== 'COMPLETED') {
            $order->update_meta_data('_prwc_payment_state', strtolower($status) ?: 'failed');
            $order->save();
            $order->add_order_note('PayPal Reader: payment did not complete (status: ' . ($status ?: 'unknown') . ').');
            wp_send_json_error(__('Payment did not complete.', 'paypal-reader-for-woocommerce'));
            return;
        }

        // Verify the amount Zettle reported matches the order total. The
        // order is server-authoritative; the browser is not trusted.
        $expected_amount = (int) round(((float) $order->get_total()) * 100);
        $reported_amount = (int) ($payload['amount'] ?? 0);
        if ($reported_amount !== $expected_amount) {
            $order->update_meta_data('_prwc_payment_state', 'amount_mismatch');
            $order->save();
            $order->add_order_note(sprintf(
                'PayPal Reader: reported amount %d does not match order total %d; rejecting.',
                $reported_amount,
                $expected_amount
            ));
            wp_send_json_error(__('Payment amount mismatch.', 'paypal-reader-for-woocommerce'));
            return;
        }

        $card_payment_uuid = (string) ($payload['CARD_PAYMENT_UUID'] ?? $payload['cardPaymentUUID'] ?? '');
        $tracking_id = (string) ($payload['trackingId'] ?? '');

        $order->update_meta_data('_prwc_payment_state', 'completed');
        $order->update_meta_data('_prwc_card_payment_uuid', $card_payment_uuid);
        $order->update_meta_data('_prwc_tracking_id', $tracking_id);
        $order->save();
        $order->add_order_note(sprintf('PayPal Reader: payment completed (card_payment_uuid=%s)', $card_payment_uuid ?: 'n/a'));

        wp_send_json_success([
            'state' => 'completed',
            'card_payment_uuid' => $card_payment_uuid,
        ]);
    }

    /**
     * Mock-mode polling endpoint. In live mode the browser drives payment
     * status over the WebSocket directly and calls confirm_payment when done,
     * so this endpoint only runs for CI.
     */
    public function check_payment_status(): void {
        if (!$this->verify_nonce('paypal_reader_nonce')) {
            return;
        }

        $order = $this->get_order_from_request();
        if (!$order || !$this->can_access_order($order)) {
            wp_send_json_error(__('Invalid order access.', 'paypal-reader-for-woocommerce'));
            return;
        }

        if (Settings::get_mode() !== 'mock') {
            wp_send_json_error(__('Polling is only available in mock mode; live payments stream status over WebSocket.', 'paypal-reader-for-woocommerce'));
            return;
        }

        $status = $this->mock_workflow_service()->get_payment_status($order->get_id(), 1);
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
        if (!$this->verify_nonce('paypal_reader_nonce')) {
            return;
        }

        $order = $this->get_order_from_request();
        if (!$order || !$this->can_access_order($order)) {
            wp_send_json_error(__('Invalid order access.', 'paypal-reader-for-woocommerce'));
            return;
        }

        if (Settings::get_mode() === 'mock') {
            $result = $this->mock_workflow_service()->cancel_payment($order->get_id());
            if (($result['state'] ?? '') === 'canceled') {
                $order->update_meta_data('_prwc_payment_state', 'canceled');
                $order->save();
            }
            wp_send_json_success($result);
            return;
        }

        // In live mode the browser sent CANCEL_PAYMENT_REQUEST over the
        // WebSocket directly; PHP just records the outcome.
        $order->update_meta_data('_prwc_payment_state', 'canceled');
        $order->save();
        $order->add_order_note('PayPal Reader: payment canceled by merchant request.');
        wp_send_json_success(['state' => 'canceled']);
    }

    public function pair_reader(): void {
        if (!$this->verify_admin()) {
            return;
        }

        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $name = isset($_POST['device_name']) ? sanitize_text_field(wp_unslash($_POST['device_name'])) : '';

        if ($code === '') {
            wp_send_json_error(__('Enter the pairing code from the reader screen.', 'paypal-reader-for-woocommerce'));
            return;
        }

        try {
            $link = $this->reader_links_service()->claim($code, $name);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
            return;
        }

        wp_send_json_success($link);
    }

    public function unpair_reader(): void {
        if (!$this->verify_admin()) {
            return;
        }

        $link_id = isset($_POST['link_id']) ? sanitize_text_field(wp_unslash($_POST['link_id'])) : '';
        if ($link_id === '') {
            wp_send_json_error(__('Missing linkId.', 'paypal-reader-for-woocommerce'));
            return;
        }

        try {
            $this->reader_links_service()->delete($link_id);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
            return;
        }

        wp_send_json_success(['linkId' => $link_id]);
    }

    private function reader_links_service(): ReaderLinksService {
        return new ReaderLinksService($this->require_zettle_client());
    }

    private function reader_session_service(): ReaderSessionService {
        return new ReaderSessionService($this->require_zettle_client());
    }

    private function require_zettle_client(): ZettleApiClient {
        $client = ZettleApiClient::from_settings();
        if (!$client) {
            throw new \RuntimeException(__('Zettle credentials are not configured. Add your client ID and assertion in the gateway settings.', 'paypal-reader-for-woocommerce'));
        }
        return $client;
    }

    private function mock_workflow_service(): PaymentWorkflowService {
        return new PaymentWorkflowService(new MockReaderService(Settings::get_gateway_settings()), new AttemptStore());
    }

    private function verify_nonce(string $action): bool {
        if (!function_exists('check_ajax_referer')) {
            return true;
        }

        if (false === check_ajax_referer($action, 'nonce', false)) {
            wp_send_json_error(__('Invalid request.', 'paypal-reader-for-woocommerce'));
            return false;
        }

        return true;
    }

    private function verify_admin(): bool {
        if (!function_exists('check_ajax_referer') || !function_exists('current_user_can')) {
            return true;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have permission to manage readers.', 'paypal-reader-for-woocommerce'));
            return false;
        }

        if (false === check_ajax_referer('paypal_reader_admin_nonce', 'nonce', false)) {
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
