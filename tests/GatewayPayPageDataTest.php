<?php

declare(strict_types=1);

/**
 * The order-pay page prints the reader script data (order key, nonce) only for a
 * request that already carries the order's key. Each case renders in its own
 * PHP process so the WordPress stubs never leak into the other tests.
 */
$render = static function (int $order_id, ?string $key = null) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/pay-page-data.php') . ' ' . $order_id;
    if ($key !== null) {
        $command .= ' ' . escapeshellarg($key);
    }
    $output = shell_exec($command . ' 2>&1');
    $decoded = json_decode((string) $output, true);
    if ($decoded === null && trim((string) $output) !== 'null') {
        throw new RuntimeException('Fixture failed: ' . $output);
    }
    return $decoded;
};

return [
    'pay page prints reader data when the request carries the order key' => function () use ($render): void {
        $data = $render(14, 'wc_order_real14');
        assert_true(is_array($data), 'Expected paypalReaderData for the right key.');
        assert_same('wc_order_real14', $data['orderKey'] ?? null);
        assert_same(14, $data['orderId'] ?? null);
    },
    'pay page prints no reader data for a wrong order key' => function () use ($render): void {
        assert_same(null, $render(14, 'wc_order_WRONGKEY0000'));
    },
    'pay page prints no reader data when the order key is missing' => function () use ($render): void {
        assert_same(null, $render(14));
    },
    'pay page prints no reader data for an empty order key' => function () use ($render): void {
        assert_same(null, $render(14, ''));
    },
    'pay page prints no reader data for an order that does not exist' => function () use ($render): void {
        assert_same(null, $render(99, 'wc_order_real14'));
    },
];
