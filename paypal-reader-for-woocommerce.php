<?php
/**
 * Plugin Name: PayPal Reader for WooCommerce
 * Description: Adds mock-first PayPal Reader support to WooCommerce for in-person payments.
 * Version:     0.0.1
 * Author:      kilbot
 * Author URI:  https://kilbot.com/
 * Update URI:  https://github.com/wcpos/paypal-reader-for-woocommerce
 * License:     GPL v2 or later
 * Text Domain: paypal-reader-for-woocommerce
 *
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 */

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('PRWC_VERSION')) {
    define('PRWC_VERSION', '0.0.1');
}

if (!defined('PRWC_PLUGIN_DIR')) {
    define('PRWC_PLUGIN_DIR', __DIR__ . '/');
}

if (!defined('PRWC_PLUGIN_URL')) {
    define('PRWC_PLUGIN_URL', 'http://localhost/wp-content/plugins/paypal-reader-for-woocommerce/');
}

spl_autoload_register(
    static function (string $class): void {
        $prefix = __NAMESPACE__ . '\\';
        $base_dir = PRWC_PLUGIN_DIR . 'includes/';

        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $base_dir . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
);


function init(): void {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    add_filter('woocommerce_payment_gateways', [Gateway::class, 'register_gateway']);
    new AjaxHandler();
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', __NAMESPACE__ . '\init', 11);
}
