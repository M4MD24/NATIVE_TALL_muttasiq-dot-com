<?php

declare(strict_types=1);

use function Pest\Laravel\artisan;

function createComposerManifestFixture(array $repositories): string
{
    $path = tempnam(sys_get_temp_dir(), 'composer-switch-');

    if ($path === false) {
        throw new \RuntimeException('Failed to create temporary composer fixture.');
    }

    $composer = [
        'name' => 'tests/composer-switch-fixture',
        'require' => [
            'php' => '^8.3',
        ],
        'repositories' => $repositories,
    ];

    file_put_contents(
        $path,
        json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    );

    return $path;
}

it('warns when local plugin path override is enabled', function () {
    $manifestPath = createComposerManifestFixture([
        'nativephp-muttasiq-patches' => [
            'type' => 'path',
            'url' => '/tmp/local-native-plugin',
        ],
    ]);

    try {
        artisan("app:verify-local-plugin-switch --skip-prompt --composer-file={$manifestPath}")
            ->expectsOutputToContain('Local plugin switch reminder')
            ->expectsOutputToContain('ENABLED')
            ->assertExitCode(0);
    } finally {
        @unlink($manifestPath);
    }
});

it('passes when local plugin path override is disabled', function () {
    $manifestPath = createComposerManifestFixture([]);

    try {
        artisan("app:verify-local-plugin-switch --skip-prompt --composer-file={$manifestPath}")
            ->expectsOutputToContain('looks good')
            ->assertExitCode(0);
    } finally {
        @unlink($manifestPath);
    }
});

it('fails when local plugin path override is enabled and confirmation is declined', function () {
    $manifestPath = createComposerManifestFixture([
        'nativephp-muttasiq-patches' => [
            'type' => 'path',
            'url' => '/tmp/local-native-plugin',
        ],
    ]);

    try {
        artisan("app:verify-local-plugin-switch --composer-file={$manifestPath}")
            ->expectsConfirmation(
                'Local path plugin repository for goodm4ven/nativephp-muttasiq-patches is still enabled. Continue anyway?',
                'no',
            )
            ->assertExitCode(1);
    } finally {
        @unlink($manifestPath);
    }
});

it('continues when local plugin path override is enabled and confirmation is accepted', function () {
    $manifestPath = createComposerManifestFixture([
        'nativephp-muttasiq-patches' => [
            'type' => 'path',
            'url' => '/tmp/local-native-plugin',
        ],
    ]);

    try {
        artisan("app:verify-local-plugin-switch --composer-file={$manifestPath}")
            ->expectsConfirmation(
                'Local path plugin repository for goodm4ven/nativephp-muttasiq-patches is still enabled. Continue anyway?',
                'yes',
            )
            ->assertExitCode(0);
    } finally {
        @unlink($manifestPath);
    }
});
