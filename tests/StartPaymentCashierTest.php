<?php

declare(strict_types=1);

/**
 * start_payment hands out the reader session (which carries the merchant's access token) only
 * to a cashier: a valid cashier payment token for this order, or a logged-in user whom WCPOS
 * lets edit the order. The order key and nonce alone are not enough.
 */
$start = static function (string $caller): array {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/start-payment-live.php') . ' ' . escapeshellarg($caller);
    $output = (string) shell_exec($command . ' 2>&1');
    $decoded = json_decode($output, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Fixture failed: ' . $output);
    }
    return $decoded;
};

$refused = static function (array $r, string $who): void {
    assert_same(false, $r['success'] ?? null, "{$who}: start_payment must be refused.");
    assert_same(false, $r['has_access_token'] ?? null, "{$who}: the merchant access token must not be returned.");
    assert_same(false, $r['saved'] ?? null, "{$who}: the order must not be written.");
};

return [
    'start payment: order key and nonce alone get no merchant token' => function () use ($start, $refused): void {
        $refused($start('key_only'), 'key only');
    },
    'start payment: a forged cashier payment token gets no merchant token' => function () use ($start, $refused): void {
        $refused($start('forged_token'), 'forged token');
    },
    'start payment: a cashier payment token for another order gets no merchant token' => function () use ($start, $refused): void {
        $refused($start('other_order'), 'other order');
    },
    'start payment: a logged-in user WCPOS does not let edit the order gets no merchant token' => function () use ($start, $refused): void {
        $refused($start('cookie_stranger'), 'stranger');
    },
    'start payment: a valid cashier payment token starts the payment' => function () use ($start): void {
        $r = $start('valid_token');
        assert_same(true, $r['success'] ?? null);
        assert_same(true, $r['has_access_token'] ?? null, 'The cashier needs the reader session to drive the reader.');
    },
    'start payment: a logged-in user WCPOS lets edit the order starts the payment' => function () use ($start): void {
        assert_same(true, $start('cookie_cashier')['success'] ?? null);
    },
];
