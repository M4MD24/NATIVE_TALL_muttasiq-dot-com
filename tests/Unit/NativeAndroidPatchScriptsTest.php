<?php

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
    $nativeRun = $root.'/.scripts/native-run-android.sh';

    expect(file_exists($nativeRun))->toBeTrue();

    $nativeRunContents = file_get_contents($nativeRun);

    expect($nativeRunContents)->not()->toContain('.scripts/native/mobile/android/patches/');
    expect($nativeRunContents)->not()->toContain('.scripts/native/mobile/support/patches/');
});

test('app service provider leaves livewire routes untouched', function () {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';

    expect(file_exists($providerPath))->toBeTrue();

    $providerContents = file_get_contents($providerPath);

    expect($providerContents)->not()->toContain('configureNativeMobileLivewireRoutes');
    expect($providerContents)->not()->toContain('Livewire::setUpdateRoute(');
    expect($providerContents)->not()->toContain('Livewire::setScriptRoute(');
});

test('native patches plugin installs request interception at document start', function () {
    $pluginCommand = '/home/goodm4ven/Code/LaravelPackages/NATIVE_PLUGIN_muttasiq-patches/src/Commands/ApplyNativePatchesCommand.php';

    expect(file_exists($pluginCommand))->toBeTrue();

    $pluginContents = file_get_contents($pluginCommand);

    expect($pluginContents)->toContain('native-request-capture');
    expect($pluginContents)->toContain('WebViewCompat.addDocumentStartJavaScript');
    expect($pluginContents)->toContain('WebViewFeature.DOCUMENT_START_SCRIPT');
    expect($pluginContents)->toContain('window.__nativePostInterceptionInstalled');
    expect($pluginContents)->toContain('isSaveEnabled = false');
    expect($pluginContents)->toContain('var lastModified: Long = if (reloadFile.exists()) reloadFile.lastModified() else 0');
});

test('native patches package is ready for publishing', function () {
    $packageRoot = '/home/goodm4ven/Code/LaravelPackages/NATIVE_PLUGIN_muttasiq-patches';
    $composerPath = $packageRoot.'/composer.json';
    $readmePath = $packageRoot.'/README.md';
    $licensePath = $packageRoot.'/LICENSE';

    expect(file_exists($composerPath))->toBeTrue();
    expect(file_exists($readmePath))->toBeTrue();
    expect(file_exists($licensePath))->toBeTrue();

    /** @var array{
     *     name: string,
     *     version: string,
     *     license: string,
     *     authors: array<int, array{name: string, email: string}>,
     *     support: array{source: string, issues: string, email: string}
     * } $composer
     */
    $composer = json_decode(file_get_contents($composerPath), true, flags: JSON_THROW_ON_ERROR);
    $readmeContents = file_get_contents($readmePath);

    expect($composer['name'])->toBe('goodm4ven/nativephp-muttasiq-patches');
    expect($composer['version'])->toBe('1.0.0');
    expect($composer['license'])->toBe('AGPL-3.0-only');
    expect($composer['authors'][0])->toMatchArray([
        'name' => 'GoodM4ven',
        'email' => 'goodm4ven@proton.me',
    ]);
    expect($composer['support'])->toMatchArray([
        'source' => 'https://github.com/GoodM4ven/nativephp-muttasiq-patches',
        'issues' => 'https://github.com/GoodM4ven/nativephp-muttasiq-patches/issues',
        'email' => 'goodm4ven@proton.me',
    ]);
    expect($readmeContents)->toContain('goodm4ven/nativephp-muttasiq-patches');
    expect($readmeContents)->toContain('pre_compile');
    expect($readmeContents)->toContain('Internal package');
});
