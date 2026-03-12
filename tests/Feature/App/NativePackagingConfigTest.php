<?php

declare(strict_types=1);

it('keeps native packaging defaults compatible with bundled web assets and native runtime fallbacks', function () {
    expect(config('nativephp.cleanup_exclude_files'))
        ->not->toContain('build');

    $previousNativeRunning = getenv('NATIVEPHP_RUNNING');
    $previousDbConnection = getenv('DB_CONNECTION');

    putenv('NATIVEPHP_RUNNING=true');
    putenv('DB_CONNECTION=mysql');
    $previousCacheStore = getenv('CACHE_STORE');
    putenv('CACHE_STORE=redis');

    try {
        /** @var array{default: string} $databaseConfig */
        $databaseConfig = require config_path('database.php');

        expect($databaseConfig['default'])->toBe('sqlite');
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
        putenv(
            $previousDbConnection === false
                ? 'DB_CONNECTION'
                : "DB_CONNECTION={$previousDbConnection}",
        );
    }
});
