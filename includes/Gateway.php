<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader;

use WCPOS\WooCommercePOS\PayPalReader\Services\ReaderLinksService;
use WCPOS\WooCommercePOS\PayPalReader\Services\ZettleApiClient;

if (!class_exists('WC_Payment_Gateway')) {
    return;
}

class Gateway extends \WC_Payment_Gateway {
    public function __construct() {
        $this->id                 = 'paypal_reader_for_woocommerce';
        $this->method_title       = __('PayPal Reader', 'paypal-reader-for-woocommerce');
        $this->method_description = __('Accept in-person card payments using a PayPal Reader (Zettle). Enable Test mode while verifying with your Zettle developer merchant account, then disable to go live.', 'paypal-reader-for-woocommerce');
        $this->has_fields         = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title', __('PayPal Reader', 'paypal-reader-for-woocommerce'));
        $this->description = $this->get_option('description', __('Pay in person using PayPal Reader.', 'paypal-reader-for-woocommerce'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_payment_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
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
            'test_mode' => [
                'title' => __('Test mode', 'paypal-reader-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Test mode', 'paypal-reader-for-woocommerce'),
                'default' => 'yes',
                'description' => __('Use the credentials from your Zettle developer merchant account while Test mode is enabled. Disable to take live payments.', 'paypal-reader-for-woocommerce'),
            ],
            'client_id' => [
                'title' => __('Zettle Client ID', 'paypal-reader-for-woocommerce'),
                'type' => 'text',
                'default' => '',
                'description' => __('Your Zettle OAuth client ID (from the Zettle Developer Portal).', 'paypal-reader-for-woocommerce'),
            ],
            'assertion' => [
                'title' => __('Zettle Assertion', 'paypal-reader-for-woocommerce'),
                'type' => 'password',
                'default' => '',
                'description' => __('Your Zettle OAuth assertion (JWT). Treated as a secret.', 'paypal-reader-for-woocommerce'),
            ],
        ];
    }

    /**
     * Append the reader-pairing UI after the standard settings table.
     */
    public function admin_options(): void {
        parent::admin_options();
        $this->render_pairing_panel();
    }

    private function render_pairing_panel(): void {
        $client = ZettleApiClient::from_settings();
        echo '<h3 class="wc-settings-sub-title">' . esc_html__('Paired readers', 'paypal-reader-for-woocommerce') . '</h3>';

        if (!$client) {
            echo '<p>' . esc_html__('Save your Zettle Client ID and Assertion above before pairing a reader.', 'paypal-reader-for-woocommerce') . '</p>';
            return;
        }

        $links = [];
        $error = '';
        try {
            $links = (new ReaderLinksService($client))->list_links();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        if ($error !== '') {
            echo '<div class="notice notice-error inline"><p>'
                . esc_html(sprintf(__('Could not load paired readers: %s', 'paypal-reader-for-woocommerce'), $error))
                . '</p></div>';
        }

        echo '<div class="paypal-reader-pairing" data-nonce="' . esc_attr(wp_create_nonce('paypal_reader_admin_nonce')) . '">';
        echo '<table class="widefat striped paypal-reader-pairing__table"><thead><tr>'
            . '<th>' . esc_html__('Reader', 'paypal-reader-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Link ID', 'paypal-reader-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Actions', 'paypal-reader-for-woocommerce') . '</th>'
            . '</tr></thead><tbody class="paypal-reader-pairing__list">';

        if (empty($links)) {
            echo '<tr class="paypal-reader-pairing__empty"><td colspan="3">' . esc_html__('No readers paired yet.', 'paypal-reader-for-woocommerce') . '</td></tr>';
        } else {
            foreach ($links as $link) {
                echo '<tr data-link-id="' . esc_attr($link['linkId']) . '">'
                    . '<td>' . esc_html($link['label']) . '</td>'
                    . '<td><code>' . esc_html($link['linkId']) . '</code></td>'
                    . '<td><button type="button" class="button paypal-reader-pairing__unpair">' . esc_html__('Unpair', 'paypal-reader-for-woocommerce') . '</button></td>'
                    . '</tr>';
            }
        }

        echo '</tbody></table>';

        echo '<h4>' . esc_html__('Pair a new reader', 'paypal-reader-for-woocommerce') . '</h4>';
        echo '<p>' . esc_html__('On the PayPal Reader, look up the pairing code (Settings → Link with a developer). Enter it below.', 'paypal-reader-for-woocommerce') . '</p>';
        echo '<p class="paypal-reader-pairing__form">'
            . '<label>' . esc_html__('Pairing code', 'paypal-reader-for-woocommerce') . ' '
            . '<input type="text" class="paypal-reader-pairing__code" autocomplete="off" /></label> '
            . '<label>' . esc_html__('Reader name', 'paypal-reader-for-woocommerce') . ' '
            . '<input type="text" class="paypal-reader-pairing__name" placeholder="' . esc_attr__('Front counter', 'paypal-reader-for-woocommerce') . '" /></label> '
            . '<button type="button" class="button button-primary paypal-reader-pairing__claim">' . esc_html__('Pair reader', 'paypal-reader-for-woocommerce') . '</button>'
            . '</p>';
        echo '<p class="paypal-reader-pairing__message" aria-live="polite"></p>';
        echo '</div>';
    }

    public function payment_fields(): void {
        echo '<div class="paypal-reader-terminal" data-gateway="paypal_reader_for_woocommerce">';
        echo '<p>' . esc_html__('Connect a reader, start the payment, and wait for the PayPal Reader result before placing the order.', 'paypal-reader-for-woocommerce') . '</p>';
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
                'testMode' => Settings::is_test_mode(),
                'strings' => [
                    'loadingReaders'    => __('Loading readers…', 'paypal-reader-for-woocommerce'),
                    'noReaders'         => __('No readers paired yet. Ask the store admin to pair one in WooCommerce → Settings → Payments → PayPal Reader.', 'paypal-reader-for-woocommerce'),
                    'startPayment'      => __('Start reader payment', 'paypal-reader-for-woocommerce'),
                    'cancelPayment'     => __('Cancel payment', 'paypal-reader-for-woocommerce'),
                    'connectingReader'  => __('Connecting to reader…', 'paypal-reader-for-woocommerce'),
                    'readerReady'       => __('Reader ready. Requesting payment…', 'paypal-reader-for-woocommerce'),
                    'paymentInProgress' => __('Payment in progress…', 'paypal-reader-for-woocommerce'),
                    'paymentComplete'   => __('Payment complete. Submitting order…', 'paypal-reader-for-woocommerce'),
                    'paymentCanceled'   => __('Payment canceled.', 'paypal-reader-for-woocommerce'),
                    'paymentFailed'     => __('Payment failed.', 'paypal-reader-for-woocommerce'),
                    'selectReader'      => __('Select a reader first.', 'paypal-reader-for-woocommerce'),
                    'testMode'          => __('TEST MODE', 'paypal-reader-for-woocommerce'),
                ],
            ]
        );
    }

    public function enqueue_admin_scripts($hook): void {
        if (!function_exists('get_current_screen')) {
            return;
        }

        // Only enqueue on the WC Payments settings screen for this gateway.
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'woocommerce_page_wc-settings') {
            return;
        }
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';
        if ($tab !== 'checkout' || $section !== $this->id) {
            return;
        }

        wp_enqueue_script(
            'paypal-reader-for-woocommerce-admin',
            PRWC_PLUGIN_URL . 'assets/js/admin.js',
            [],
            PRWC_VERSION,
            true
        );

        wp_enqueue_style(
            'paypal-reader-for-woocommerce-admin',
            PRWC_PLUGIN_URL . 'assets/css/admin.css',
            [],
            PRWC_VERSION
        );

        wp_localize_script(
            'paypal-reader-for-woocommerce-admin',
            'paypalReaderAdminData',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'strings' => [
                    'pairing'    => __('Pairing reader…', 'paypal-reader-for-woocommerce'),
                    'paired'     => __('Reader paired.', 'paypal-reader-for-woocommerce'),
                    'unpairing'  => __('Unpairing reader…', 'paypal-reader-for-woocommerce'),
                    'unpaired'   => __('Reader unpaired.', 'paypal-reader-for-woocommerce'),
                    'confirmUnpair' => __('Unpair this reader?', 'paypal-reader-for-woocommerce'),
                    'enterCode'  => __('Enter the pairing code first.', 'paypal-reader-for-woocommerce'),
                ],
            ]
        );
    }

    /**
     * Warn admins when the CI-only mock reader path is active (via the
     * PRWC_USE_MOCK_READER constant or filter). Catches the common foot-gun
     * of leaving the constant defined when promoting wp-config from a
     * dev/CI environment to staging or production.
     *
     * Registered from the plugin bootstrap so the notice runs on every admin
     * page, independent of whether WooCommerce has instantiated the gateway.
     */
    public static function maybe_render_mock_reader_notice(): void {
        if (!Settings::use_mock_reader()) {
            return;
        }

        if (function_exists('current_user_can') && !current_user_can('manage_woocommerce')) {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>'
            . esc_html__('PayPal Reader: mock reader is active.', 'paypal-reader-for-woocommerce')
            . '</strong> '
            . esc_html__('PRWC_USE_MOCK_READER is defined in wp-config.php, so no real reader payments will be processed. Remove that constant before going live.', 'paypal-reader-for-woocommerce')
            . '</p></div>';
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
        // Settings::get_mode() already enforces credential presence before
        // returning 'live', so we do not re-check credentials here.
        return parent::is_available();
    }
}
