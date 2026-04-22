<?php

declare(strict_types=1);

function assert_same($expected, $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message !== '' ? $message : sprintf('Failed asserting that %s is identical to %s.', var_export($actual, true), var_export($expected, true)));
    }
}

function assert_true($condition, string $message = 'Failed asserting that condition is true.'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_count_is(int $expected, array $actual, string $message = ''): void {
    $count = count($actual);
    if ($count !== $expected) {
        throw new RuntimeException($message !== '' ? $message : sprintf('Failed asserting count %d matches expected %d.', $count, $expected));
    }
}
