<?php

declare(strict_types=1);

it('does not exclude vite build output from native bundle cleanup', function () {
    expect(config('nativephp.cleanup_exclude_files'))
        ->not->toContain('build');
});

it('uses sqlite as default connection when running inside native runtime', function () {
    $previousNativeRunning = getenv('NATIVEPHP_RUNNING');
    $previousDbConnection = getenv('DB_CONNECTION');

    putenv('NATIVEPHP_RUNNING=true');
    putenv('DB_CONNECTION=mysql');

    try {
        /** @var array{default: string} $databaseConfig */
        $databaseConfig = require config_path('database.php');

        expect($databaseConfig['default'])->toBe('sqlite');
    } finally {
        putenv(
            $previousNativeRunning === false
                ? 'NATIVEPHP_RUNNING'
                : "NATIVEPHP_RUNNING={$previousNativeRunning}",
        );
        putenv(
            $previousDbConnection === false
                ? 'DB_CONNECTION'
                : "DB_CONNECTION={$previousDbConnection}",
        );
    }
});

it('uses file cache store when running inside native runtime', function () {
    $previousNativeRunning = getenv('NATIVEPHP_RUNNING');
    $previousCacheStore = getenv('CACHE_STORE');

    putenv('NATIVEPHP_RUNNING=true');
    putenv('CACHE_STORE=redis');

    try {
        /** @var array{default: string} $cacheConfig */
        $cacheConfig = require config_path('cache.php');

        expect($cacheConfig['default'])->toBe('file');
    } finally {
        putenv(
            $previousNativeRunning === false
                ? 'NATIVEPHP_RUNNING'
                : "NATIVEPHP_RUNNING={$previousNativeRunning}",
        );
        putenv(
            $previousCacheStore === false
                ? 'CACHE_STORE'
                : "CACHE_STORE={$previousCacheStore}",
        );
    }
});
