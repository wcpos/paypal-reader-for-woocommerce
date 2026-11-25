<?php

declare(strict_types=1);

/** Lines of the release.yml step whose header line trims to $marker, from that line to the step's end. */
function release_step_lines(string $marker): array {
    $lines = file(__DIR__ . '/../.github/workflows/release.yml', FILE_IGNORE_NEW_LINES);
    foreach ($lines as $i => $line) {
        if (trim($line) !== $marker) {
            continue;
        }
        $indent = strlen($line) - strlen(ltrim($line, ' -'));
        $step = [];
        foreach (array_slice($lines, $i + 1) as $next) {
            if (trim($next) !== '' && strspn($next, ' ') < $indent) {
                break;
            }
            $step[] = $next;
        }
        return ['indent' => $indent, 'lines' => $step];
    }
    throw new RuntimeException("release.yml has no step marked '{$marker}'.");
}

/** Runs the check_release step's script with a stub gh; returns [exit status, GITHUB_OUTPUT content]. */
function run_release_check(string $ghOutput, int $ghStatus): array {
    $step = release_step_lines('id: check_release');
    $body = null;
    foreach ($step['lines'] as $line) {
        if ($body === null) {
            if (strspn($line, ' ') === $step['indent'] && trim($line) === 'run: |') {
                $body = [];
            }
            continue;
        }
        if (trim($line) !== '' && strspn($line, ' ') <= $step['indent']) {
            break;
        }
        $body[] = $line;
    }
    if ($body === null) {
        throw new RuntimeException('The check_release step has no run block.');
    }
    $margin = min(array_map(fn ($l) => strspn($l, ' '), array_filter($body, fn ($l) => trim($l) !== '')));
    $script = implode("\n", array_map(fn ($l) => (string) substr($l, $margin), $body)) . "\n";
    $script = strtr($script, [
        '${{ steps.build_zip.outputs.version }}' => '0.1.0',
        '${{ steps.build_zip.outputs.versioned_zip }}' => '/x/dist/paypal-reader-for-woocommerce-0.1.0.zip',
    ]);

    $dir = sys_get_temp_dir() . '/release-check-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700);
    try {
        file_put_contents("{$dir}/gh", "#!/bin/sh\nprintf '%s' " . escapeshellarg($ghOutput) . "\nexit {$ghStatus}\n");
        chmod("{$dir}/gh", 0755);
        file_put_contents("{$dir}/step.sh", $script);
        file_put_contents("{$dir}/output", '');
        $env = [
            'PATH' => $dir . ':' . getenv('PATH'),
            'GITHUB_REPOSITORY' => 'wcpos/paypal-reader-for-woocommerce',
            'GITHUB_OUTPUT' => "{$dir}/output",
        ];
        $null = ['file', '/dev/null', 'w'];
        $process = proc_open(['bash', '-e', "{$dir}/step.sh"], [0 => ['file', '/dev/null', 'r'], 1 => $null, 2 => $null], $pipes, $dir, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start bash.');
        }
        $status = proc_close($process);
        return [$status, file_get_contents("{$dir}/output")];
    } finally {
        foreach (['gh', 'step.sh', 'output'] as $file) {
            @unlink("{$dir}/{$file}");
        }
        rmdir($dir);
    }
}

return [
    'release check skips when the exact zip is already published' => function (): void {
        assert_same([0, "release=false\n"], run_release_check("paypal-reader-for-woocommerce-0.1.0.zip\n", 0));
    },
    'release check releases when no asset is listed' => function (): void {
        assert_same([0, "release=true\n"], run_release_check('', 0));
    },
    'release check releases when only a longer name ends in the zip name' => function (): void {
        assert_same([0, "release=true\n"], run_release_check("old-paypal-reader-for-woocommerce-0.1.0.zip\n", 0));
    },
    'release check fails and writes nothing when the API call fails' => function (): void {
        [$status, $output] = run_release_check('', 1);
        assert_true($status !== 0, 'The check step must fail when gh api fails.');
        assert_same('', $output);
    },
    'release upload runs only when the check says release=true' => function (): void {
        $step = release_step_lines('- name: Create or update GitHub release');
        $if = "if: github.ref == 'refs/heads/main' && steps.check_release.outputs.release == 'true'";
        assert_true(in_array(str_repeat(' ', $step['indent']) . $if, $step['lines'], true), 'The upload step lost its release guard.');
    },
];
