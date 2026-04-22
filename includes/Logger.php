<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader;

class Logger {
    public const WC_LOG_FILENAME = 'paypal-reader-for-woocommerce';

    public static function log($message): void {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();
        if (!is_string($message)) {
            $message = print_r($message, true);
        }

        $logger->info($message, ['source' => self::WC_LOG_FILENAME]);
    }
}
