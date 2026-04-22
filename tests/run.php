<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$tests = [];
foreach (glob(__DIR__ . '/*Test.php') as $file) {
    $cases = require $file;
    foreach ($cases as $name => $test) {
        $tests[$name] = $test;
    }
}

$passed = 0;
$failed = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        fwrite(STDOUT, "PASS {$name}\n");
        $passed++;
    } catch (Throwable $throwable) {
        fwrite(STDERR, "FAIL {$name}: {$throwable->getMessage()}\n");
        $failed++;
    }
}

fwrite(STDOUT, sprintf("\nTests: %d passed, %d failed\n", $passed, $failed));
exit($failed > 0 ? 1 : 0);
