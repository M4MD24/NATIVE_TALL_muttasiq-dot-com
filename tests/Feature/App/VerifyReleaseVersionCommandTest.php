<?php

use function Pest\Laravel\artisan;

it('warns when native version values look unchanged', function () {
    config([
        'nativephp.version' => 'DEBUG',
        'nativephp.version_code' => 1,
    ]);

    artisan('app:verify-release-version --skip-prompt')
        ->expectsOutputToContain('Release version reminder')
        ->assertExitCode(0);
});

it('passes when native version values look bumped', function () {
    config([
        'nativephp.version' => '2.0.0',
        'nativephp.version_code' => 5,
    ]);

    artisan('app:verify-release-version --skip-prompt')
        ->expectsOutputToContain('looks good')
        ->assertExitCode(0);
});
