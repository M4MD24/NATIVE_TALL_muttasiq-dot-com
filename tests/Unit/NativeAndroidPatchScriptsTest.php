<?php

test('native patches plugin is registered for android builds', function () {
    $provider = new \App\Providers\NativeServiceProvider(app());
    $plugins = $provider->plugins();

    expect($plugins)->toContain('Goodm4ven\\NativePatches\\NativePatchesServiceProvider');
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
    $pluginCommand = '/home/goodm4ven/Code/LaravelPackages/NATIVE_PLUGIN_muttasiq/src/Commands/ApplyNativePatchesCommand.php';

    expect(file_exists($pluginCommand))->toBeTrue();

    $pluginContents = file_get_contents($pluginCommand);

    expect($pluginContents)->toContain('native-request-capture');
    expect($pluginContents)->toContain('WebViewCompat.addDocumentStartJavaScript');
    expect($pluginContents)->toContain('WebViewFeature.DOCUMENT_START_SCRIPT');
    expect($pluginContents)->toContain('window.__nativePostInterceptionInstalled');
    expect($pluginContents)->toContain('isSaveEnabled = false');
    expect($pluginContents)->toContain('var lastModified: Long = if (reloadFile.exists()) reloadFile.lastModified() else 0');
});
