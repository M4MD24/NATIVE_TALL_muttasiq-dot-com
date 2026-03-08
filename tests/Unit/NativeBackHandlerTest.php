<?php

test('native back action exposes exit hint for root view', function () {
    $path = dirname(__DIR__, 2).'/resources/js/packages/alpine/hash-actions.js';
    $script = dirname(__DIR__, 2).'/.scripts/native/mobile/android/patches/back-handler.sh';

    expect(file_exists($path))->toBeTrue();
    expect(file_exists($script))->toBeTrue();

    $contents = file_get_contents($path);

    expect($contents)->toContain('shouldExit');
    expect($contents)->toContain('window.__nativeBackAction');

    $output = [];
    $status = null;
    exec('bash -n '.escapeshellarg($script), $output, $status);

    expect($status)->toBe(0);
});
