<?php

test('android patch scripts are present and valid', function () {
    $root = dirname(__DIR__, 2);
    $systemUi = $root.'/.scripts/native/mobile/android/patches/system-ui.sh';
    $livewireGuard = $root.'/.scripts/native/mobile/android/patches/livewire-guard.sh';
    $nativeRun = $root.'/.scripts/native-run-android.sh';

    expect(file_exists($systemUi))->toBeTrue();
    expect(file_exists($livewireGuard))->toBeTrue();
    expect(file_exists($nativeRun))->toBeTrue();

    $output = [];
    $status = null;
    exec('bash -n '.escapeshellarg($systemUi), $output, $status);
    expect($status)->toBe(0);

    $output = [];
    $status = null;
    exec('bash -n '.escapeshellarg($livewireGuard), $output, $status);
    expect($status)->toBe(0);

    $nativeRunContents = file_get_contents($nativeRun);
    expect($nativeRunContents)->toContain('livewire-guard.sh');
});
