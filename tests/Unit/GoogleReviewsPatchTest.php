<?php

test('composer local plugin switch script is syntactically valid', function () {
    $root = dirname(__DIR__, 2);
    $script = $root.'/.scripts/composer-local-plugins-switch.sh';

    expect(file_exists($script))->toBeTrue();

    $output = [];
    $status = null;
    exec('bash -n '.escapeshellarg($script), $output, $status);

    expect($status)->toBe(0);
});
