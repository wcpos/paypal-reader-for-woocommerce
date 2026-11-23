<?php

declare(strict_types=1);

/** Confirm requires a cashier and the matching, unpaid, pending payment attempt. */
$confirm = static function (string $caller): array {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/confirm-payment-live.php') . ' ' . escapeshellarg($caller);
    $output = (string) shell_exec($command . ' 2>&1');
    $decoded = json_decode($output, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Fixture failed: ' . $output);
    }
    return $decoded;
};

$refused = static function (array $r, string $who, bool $unwritten = true): void {
    assert_same(false, $r['success'] ?? null, "{$who}: confirm_payment must be refused.");
    assert_same(false, $r['completed'] ?? null, "{$who}: the order must not be set to completed.");
    if ($unwritten) {
        assert_same(false, $r['saved'] ?? null, "{$who}: the order must not be written.");
    }
};

return [
    'confirm payment: order key and nonce alone are refused without writes' => function () use ($confirm, $refused): void {
        $refused($confirm('key_only'), 'key only');
    },
    'confirm payment: an expired cashier token is refused without writes' => function () use ($confirm, $refused): void {
        $refused($confirm('expired_token'), 'expired token');
    },
    'confirm payment: a token for another order is refused without writes' => function () use ($confirm, $refused): void {
        $refused($confirm('other_order_token'), 'other order token');
    },
    'confirm payment: an order with no started attempt is refused without writes' => function () use ($confirm, $refused): void {
        $refused($confirm('no_start'), 'no start');
    },
    'confirm payment: a different trace is refused without writes' => function () use ($confirm, $refused): void {
        $refused($confirm('wrong_trace'), 'wrong trace');
    },
    'confirm payment: a missing posted trace is refused without writes' => function () use ($confirm, $refused): void {
        $refused($confirm('no_trace_posted'), 'no trace posted');
    },
    'confirm payment: an already completed attempt is refused without writes' => function () use ($confirm, $refused): void {
        $refused($confirm('replayed'), 'replayed');
    },
    'confirm payment: a paid order is refused without writes' => function () use ($confirm, $refused): void {
        $refused($confirm('paid'), 'paid');
    },
    'confirm payment: a changed order total is refused even when the reported amount matches' => function () use ($confirm, $refused): void {
        $refused($confirm('total_changed'), 'total changed', false);
    },
    'confirm payment: a valid cashier token completes the matching attempt' => function () use ($confirm): void {
        $r = $confirm('valid_token');
        assert_same(true, $r['success'] ?? null);
        assert_same(true, $r['completed'] ?? null);
    },
    'confirm payment: a logged-in user WCPOS lets edit the order confirms the matching attempt' => function () use ($confirm): void {
        assert_same(true, $confirm('cookie_cashier')['success'] ?? null);
    },
    'confirm payment: a mismatched reported amount is refused' => function () use ($confirm, $refused): void {
        $refused($confirm('reported_mismatch'), 'reported mismatch', false);
    },
    'confirm payment: missing stored and posted traces are refused without writes' => function () use ($confirm, $refused): void {
        $refused($confirm('no_trace_both'), 'no trace both');
    },
    'confirm payment: a lowercase completed status is refused without completing the order' => function () use ($confirm, $refused): void {
        $refused($confirm('lowercase_completed'), 'lowercase completed', false);
    },
    'cancel payment: order key and nonce alone are refused without writes' => function () use ($confirm): void {
        $r = $confirm('cancel_key_only');
        assert_same(false, $r['success'] ?? null);
        assert_same(false, $r['canceled'] ?? null);
        assert_same(false, $r['saved'] ?? null);
    },
    'cancel payment: a valid cashier token cancels the payment' => function () use ($confirm): void {
        $r = $confirm('cancel_valid_token');
        assert_same(true, $r['success'] ?? null);
        assert_same(true, $r['canceled'] ?? null);
    },
];
