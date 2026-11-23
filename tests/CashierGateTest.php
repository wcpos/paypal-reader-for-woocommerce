<?php

declare(strict_types=1);

/**
 * The cashier gate: WCPOS's own cashier checks at render, then a short-lived payment token
 * bound to the order, its key and the cashier. Each scenario runs in its own PHP process.
 */
$gate = static function (string $scenario): array {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/cashier-gate.php') . ' ' . escapeshellarg($scenario);
    $output = (string) shell_exec($command . ' 2>&1');
    $decoded = json_decode($output, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Fixture failed: ' . $output);
    }
    return $decoded;
};

return [
    'cashier gate: a valid WCPOS cashier token names the cashier and mints a payment token' => function () use ($gate): void {
        $r = $gate('valid');
        assert_same(7, $r['user'] ?? null);
        assert_same(true, $r['token_is_string'] ?? null);
        assert_same(true, $r['verifies'] ?? null);
        assert_same(true, $r['verifies_near_expiry'] ?? null, 'A token must still verify at its last second.');
    },
    'cashier gate: a payment token lives at most 15 minutes' => function () use ($gate): void {
        $r = $gate('valid');
        assert_true(is_int($r['ttl'] ?? null) && $r['ttl'] > 0 && $r['ttl'] <= 900, 'TTL must be between 1 and 900 seconds.');
    },
    'cashier gate: no WCPOS token names nobody' => function () use ($gate): void {
        assert_same(0, $gate('no_token')['user'] ?? null);
    },
    'cashier gate: a WCPOS token that WCPOS refuses names nobody' => function () use ($gate): void {
        assert_same(0, $gate('forged_jwt')['user'] ?? null);
    },
    'cashier gate: an expired payment token is refused' => function () use ($gate): void {
        assert_same(false, $gate('expired')['verifies'] ?? null);
    },
    'cashier gate: a payment token that expires beyond one TTL is refused' => function () use ($gate): void {
        assert_same(false, $gate('far_future')['verifies'] ?? null);
    },
    'cashier gate: a forged or tampered payment token is refused' => function () use ($gate): void {
        $r = $gate('tampered');
        assert_same(false, $r['user_swapped'] ?? null, 'Swapping the user must break the token.');
        assert_same(false, $r['sig_flipped'] ?? null, 'Changing the signature must break the token.');
        assert_same(false, $r['garbage'] ?? null);
        assert_same(false, $r['empty'] ?? null);
    },
    'cashier gate: a payment token for another order, or for a re-keyed order, is refused' => function () use ($gate): void {
        $r = $gate('other_order');
        assert_same(false, $r['other_order'] ?? null);
        assert_same(false, $r['order_rekeyed'] ?? null);
    },
    'cashier gate: a cashier without POS access is refused' => function () use ($gate): void {
        assert_same(0, $gate('no_pos_access')['user'] ?? null);
    },
    'cashier gate: a cashier who may not edit the order is refused' => function () use ($gate): void {
        assert_same(0, $gate('no_order_edit')['user'] ?? null);
    },
    'cashier gate: a payment token stops working once its cashier loses POS access' => function () use ($gate): void {
        assert_same(false, $gate('lost_pos_access')['verifies'] ?? null);
    },
    'cashier gate: a verdict that is not exactly true is refused' => function () use ($gate): void {
        assert_same(0, $gate('verdict_wrong_type')['user'] ?? null);
    },
    'cashier gate: a decoded WCPOS token of the wrong shape is refused' => function () use ($gate): void {
        assert_same(0, $gate('auth_wrong_type')['user'] ?? null);
    },
    'cashier gate: an error thrown by WCPOS is refused and logged' => function () use ($gate): void {
        $r = $gate('auth_throws');
        assert_same(0, $r['user'] ?? null);
        assert_same(1, $r['logs'] ?? null, 'Expected exactly one log line.');
    },
    'cashier gate: missing WCPOS classes refuse and log' => function () use ($gate): void {
        $r = $gate('classes_absent');
        assert_same(0, $r['user'] ?? null);
        assert_same(false, $r['may'] ?? null);
        assert_true(($r['logs'] ?? 0) >= 1, 'Expected a log line when WCPOS classes are missing.');
    },
    'cashier gate: missing WCPOS classes refuse an otherwise valid payment token' => function () use ($gate): void {
        assert_same(false, $gate('classes_absent_verify')['verifies'] ?? null);
    },
    'cashier gate: only a user WCPOS lets edit the order may take payment' => function () use ($gate): void {
        $r = $gate('may_take_payment');
        assert_same(true, $r['cashier'] ?? null);
        assert_same(false, $r['stranger'] ?? null);
        assert_same(false, $r['nobody'] ?? null);
    },
];
