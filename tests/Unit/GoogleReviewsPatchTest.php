<?php

test('google reviews patch script is syntactically valid', function () {
    $script = dirname(__DIR__, 2).'/.scripts/native/mobile/android/patches/google-reviews.sh';

    expect(file_exists($script))->toBeTrue();

    $output = [];
    $status = null;
    exec('bash -n '.escapeshellarg($script), $output, $status);

    expect($status)->toBe(0);
});
