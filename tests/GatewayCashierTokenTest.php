<?php

declare(strict_types=1);

/**
 * The order-pay page carries a cashier payment token only when it renders inside the WCPOS
 * pay template for a cashier JWT that WCPOS accepts. The merchant's Zettle access token is
 * never part of the page data.
 */
$page = static function (string $context, string $arg = '', string $jwt = '') {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/pay-page-cashier.php')
        . ' ' . escapeshellarg($context) . ' ' . escapeshellarg($arg) . ' ' . escapeshellarg($jwt);
    $output = (string) shell_exec($command . ' 2>&1');
    $decoded = json_decode($output, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Fixture failed: ' . $output);
    }
    return $decoded;
};

$no_merchant_token = static function (array $data): void {
    foreach (['accessToken', 'access_token'] as $key) {
        assert_true(!array_key_exists($key, $data), "Page data must not carry {$key}.");
    }
};

return [
    'pay page: inside the WCPOS template a valid cashier JWT (token) yields a cashier payment token' => function () use ($page, $no_merchant_token): void {
        $data = $page('wcpos', 'token', 'jwt-cashier-7');
        $token = $data['cashierToken'] ?? null;
        assert_true(is_string($token) && $token !== '', 'Expected a cashier payment token.');
        assert_true(strpos($token, 'jwt-cashier-7') === false, 'The WCPOS JWT itself must not be echoed back.');
        $no_merchant_token($data);
    },
    'pay page: inside the WCPOS template a valid cashier JWT (wcpos_jwt) yields a cashier payment token' => function () use ($page): void {
        $token = $page('wcpos', 'wcpos_jwt', 'jwt-cashier-7')['cashierToken'] ?? null;
        assert_true(is_string($token) && $token !== '', 'Expected a cashier payment token.');
    },
    'pay page: a JWT WCPOS refuses yields no cashier payment token' => function () use ($page, $no_merchant_token): void {
        $data = $page('wcpos', 'token', 'jwt-forged');
        assert_same('', $data['cashierToken'] ?? null);
        $no_merchant_token($data);
    },
    'pay page: no JWT yields no cashier payment token' => function () use ($page): void {
        assert_same('', $page('wcpos')['cashierToken'] ?? null);
    },
    'pay page: outside the WCPOS template even a valid JWT yields no cashier payment token' => function () use ($page, $no_merchant_token): void {
        $data = $page('storefront', 'token', 'jwt-cashier-7');
        assert_same('', $data['cashierToken'] ?? null);
        $no_merchant_token($data);
    },
];
