<?php

/**
 * @return array{name?: string, version?: string}
 */
function nativePatchesLockPackage(): array
{
    $lockFile = dirname(__DIR__, 2).'/composer.lock';

    /** @var array{packages?: array<int, array{name?: string, version?: string}>} $lock */
    $lock = json_decode(file_get_contents($lockFile), true, flags: JSON_THROW_ON_ERROR);

    foreach ($lock['packages'] ?? [] as $package) {
        if (($package['name'] ?? null) === 'goodm4ven/nativephp-muttasiq-patches') {
            return $package;
        }
    }

    return [];
}

test('native patches plugin is registered for android builds', function () {
    $provider = new \App\Providers\NativeServiceProvider(app());
    $plugins = $provider->plugins();

    expect($plugins)->toContain('Goodm4ven\\NativePatches\\NativePatchesServiceProvider');
});

test('native patches hook command is registered with artisan', function () {
    $providersPath = dirname(__DIR__, 2).'/bootstrap/providers.php';
    $providersContents = file_get_contents($providersPath);
    $commandOutput = [];
    $status = null;

    exec('php artisan nativephp:muttasiq:patches --help', $commandOutput, $status);

    expect($providersContents)->toContain('Goodm4ven\\NativePatches\\NativePatchesServiceProvider::class');
    expect($status)->toBe(0);
    expect(implode("\n", $commandOutput))->toContain('nativephp:muttasiq:patches');
});

test('native run script relies on plugin patches', function () {
    $root = dirname(__DIR__, 2);
    $androidScripts = [
        $root.'/.scripts/run-android.sh',
        $root.'/.scripts/watch-android.sh',
        $root.'/.scripts/share-android.sh',
    ];

    foreach ($androidScripts as $script) {
        expect(file_exists($script))->toBeTrue();

        $contents = file_get_contents($script);

        expect($contents)->not()->toContain('.scripts/native/mobile/android/patches/');
        expect($contents)->not()->toContain('.scripts/native/mobile/support/patches/edge-components.sh');
    }

    $nativeShareContents = file_get_contents($root.'/.scripts/share-android.sh');
    expect($nativeShareContents)->toContain('.scripts/native/mobile/support/patches/jump-status-texts.sh');
});

test('app service provider leaves livewire routes untouched', function () {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';

    expect(file_exists($providerPath))->toBeTrue();

    $providerContents = file_get_contents($providerPath);

    expect($providerContents)->not()->toContain('configureNativeMobileLivewireRoutes');
    expect($providerContents)->not()->toContain('Livewire::setUpdateRoute(');
    expect($providerContents)->not()->toContain('Livewire::setScriptRoute(');
});

test('native patches package stays pinned in app dependencies', function () {
    $composerPath = dirname(__DIR__, 2).'/composer.json';

    /** @var array{require?: array<string, string>} $composer */
    $composer = json_decode(file_get_contents($composerPath), true, flags: JSON_THROW_ON_ERROR);
    $lockedPackage = nativePatchesLockPackage();

    expect($composer['require'] ?? [])
        ->toHaveKey('goodm4ven/nativephp-muttasiq-patches', '^1.0.0');

    expect($lockedPackage)
        ->toMatchArray(['name' => 'goodm4ven/nativephp-muttasiq-patches']);

    expect((string) ($lockedPackage['version'] ?? ''))
        ->toStartWith('v1.');
});

test('composer local plugin switch script toggles the muttasiq patches package by default', function () {
    $root = dirname(__DIR__, 2);
    $script = file_get_contents($root.'/.scripts/composer-local-plugins-switch.sh');

    expect($script)->toContain('goodm4ven/nativephp-muttasiq-patches');
    expect($script)->toContain('${HOME}/Code/LaravelPackages/NATIVE_PLUGIN_muttasiq-patches');
    expect($script)->toContain('current_repository="$(composer config "repositories.${repository_key}" 2>/dev/null || true)"');
    expect($script)->toContain('grep -Fq \'"type":"path"\' <<<"${current_repository}"');
    expect($script)->toContain('composer config --unset "repositories.${repository_key}"');
    expect($script)->toContain('composer config "repositories.${repository_key}" path "${package_path}"');
    expect($script)->toContain('composer update "${package_name}" --with-all-dependencies');
});

test('android log script writes into storage logs', function () {
    $root = dirname(__DIR__, 2);
    $script = file_get_contents($root.'/.scripts/log-android.sh');

    expect($script)->toContain('output_dir="${project_root}/storage/logs"');
    expect($script)->toContain('output_file="${output_dir}/log-android.txt"');
});
