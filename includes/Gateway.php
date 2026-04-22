<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader;

use WCPOS\WooCommercePOS\PayPalReader\Services\AttemptStore;
use WCPOS\WooCommercePOS\PayPalReader\Services\MockReaderService;
use WCPOS\WooCommercePOS\PayPalReader\Services\PaymentWorkflowService;

if (!class_exists('WC_Payment_Gateway')) {
    return;
}

class Gateway extends \WC_Payment_Gateway {
    public function __construct() {
        $this->id                 = 'paypal_reader_for_woocommerce';
        $this->method_title       = __('PayPal Reader', 'paypal-reader-for-woocommerce');
        $this->method_description = __('Accept in-person payments using a mock-first PayPal Reader workflow.', 'paypal-reader-for-woocommerce');
        $this->has_fields         = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title', __('PayPal Reader', 'paypal-reader-for-woocommerce'));
        $this->description = $this->get_option('description', __('Pay in person using PayPal Reader.', 'paypal-reader-for-woocommerce'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_payment_scripts']);
    }

    public static function register_gateway(array $methods): array {
        $methods[] = self::class;
        return $methods;
    }

    public function init_form_fields(): void {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'paypal-reader-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal Reader for web checkout (not necessary for WCPOS)', 'paypal-reader-for-woocommerce'),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'paypal-reader-for-woocommerce'),
                'type' => 'text',
                'default' => __('PayPal Reader', 'paypal-reader-for-woocommerce'),
            ],
            'description' => [
                'title' => __('Description', 'paypal-reader-for-woocommerce'),
                'type' => 'textarea',
                'default' => __('Pay in person using PayPal Reader.', 'paypal-reader-for-woocommerce'),
            ],
            'mode' => [
                'title' => __('Mode', 'paypal-reader-for-woocommerce'),
                'type' => 'select',
                'default' => 'mock',
                'options' => [
                    'mock' => __('Mock mode', 'paypal-reader-for-woocommerce'),
                    'live' => __('Live mode (credentials required)', 'paypal-reader-for-woocommerce'),
                ],
                'description' => __('Mock mode is the primary testing path for this plugin when no real reader is available.', 'paypal-reader-for-woocommerce'),
            ],
            'client_id' => [
                'title' => __('Zettle Client ID', 'paypal-reader-for-woocommerce'),
                'type' => 'text',
                'default' => '',
            ],
            'assertion' => [
                'title' => __('Zettle Assertion', 'paypal-reader-for-woocommerce'),
                'type' => 'password',
                'default' => '',
            ],
            'mock_reader_name' => [
                'title' => __('Mock Reader Name', 'paypal-reader-for-woocommerce'),
                'type' => 'text',
                'default' => 'WCPOS Mock Reader',
            ],
            'mock_cancel_behavior' => [
                'title' => __('Mock Cancel Behaviour', 'paypal-reader-for-woocommerce'),
                'type' => 'select',
                'default' => 'canceled',
                'options' => [
                    'canceled' => __('Cancel succeeds', 'paypal-reader-for-woocommerce'),
                    'too_late' => __('Cancel is too late and payment still completes', 'paypal-reader-for-woocommerce'),
                ],
            ],
        ];
    }

    public function payment_fields(): void {
        echo '<div class="paypal-reader-terminal" data-gateway="paypal_reader_for_woocommerce">';
        echo '<p>' . esc_html__('Connect a reader, start the payment, and wait for the mock PayPal Reader result before placing the order.', 'paypal-reader-for-woocommerce') . '</p>';
        echo '<div class="paypal-reader-terminal__status" aria-live="polite"></div>';
        echo '<div class="paypal-reader-terminal__readers"></div>';
        echo '<div class="paypal-reader-terminal__actions">';
        echo '<button type="button" class="button paypal-reader-terminal__start">' . esc_html__('Start reader payment', 'paypal-reader-for-woocommerce') . '</button>';
        echo '<button type="button" class="button paypal-reader-terminal__cancel" disabled>' . esc_html__('Cancel payment', 'paypal-reader-for-woocommerce') . '</button>';
        echo '</div>';
        echo '<pre class="paypal-reader-terminal__log"></pre>';
        echo '</div>';
    }

    public function enqueue_payment_scripts(): void {
        if (!function_exists('get_query_var') || !function_exists('wc_get_order')) {
            return;
        }

        $order_id = (int) get_query_var('order-pay');
        if ($order_id <= 0) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        wp_enqueue_script(
            'paypal-reader-for-woocommerce-payment',
            PRWC_PLUGIN_URL . 'assets/js/payment.js',
            [],
            PRWC_VERSION,
            true
        );

        wp_enqueue_style(
            'paypal-reader-for-woocommerce-payment',
            PRWC_PLUGIN_URL . 'assets/css/payment.css',
            [],
            PRWC_VERSION
        );

        wp_localize_script(
            'paypal-reader-for-woocommerce-payment',
            'paypalReaderData',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('paypal_reader_nonce'),
                'orderId' => $order_id,
                'orderKey' => $order->get_order_key(),
                'amount' => (int) round(((float) $order->get_total()) * 100),
                'currency' => $order->get_currency(),
                'strings' => [
                    'loadingReaders' => __('Loading readers…', 'paypal-reader-for-woocommerce'),
                    'startPayment' => __('Start reader payment', 'paypal-reader-for-woocommerce'),
                    'cancelPayment' => __('Cancel payment', 'paypal-reader-for-woocommerce'),
                    'paymentComplete' => __('Payment complete. Submitting order…', 'paypal-reader-for-woocommerce'),
                    'paymentCanceled' => __('Payment canceled.', 'paypal-reader-for-woocommerce'),
                    'selectReader' => __('Select a reader first.', 'paypal-reader-for-woocommerce'),
                ],
            ]
        );
    }

    public function process_payment($order_id): array {
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice(__('Invalid order.', 'paypal-reader-for-woocommerce'), 'error');
            return ['result' => 'failure'];
        }

        $state = (string) $order->get_meta('_prwc_payment_state');
        if ($state !== 'completed') {
            wc_add_notice(__('Complete the PayPal Reader payment before placing the order.', 'paypal-reader-for-woocommerce'), 'error');
            return ['result' => 'failure'];
        }

        $transaction_id = (string) $order->get_meta('_prwc_card_payment_uuid');
        if ($transaction_id !== '') {
            $order->set_transaction_id($transaction_id);
        }

        if (!$order->is_paid()) {
            $order->payment_complete($transaction_id !== '' ? $transaction_id : null);
        }

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    public function is_available(): bool {
        $settings = Settings::get_gateway_settings();
        $mode = Settings::get_mode($settings);

        if ($mode === 'mock') {
            return parent::is_available();
        }

        return parent::is_available() && !empty($settings['client_id']) && !empty($settings['assertion']);
    }
}
