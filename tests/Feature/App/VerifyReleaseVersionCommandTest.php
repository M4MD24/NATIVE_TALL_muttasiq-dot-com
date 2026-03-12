<?php

use function Pest\Laravel\artisan;

it('warns for unchanged native release values and passes when values are bumped', function () {
    config([
        'nativephp.version' => 'DEBUG',
        'nativephp.version_code' => 1,
    ]);

    artisan('app:verify-release-version --skip-prompt')
        ->expectsOutputToContain('Release version reminder')
        ->assertExitCode(0);

    config([
        'nativephp.version' => '2.0.0',
        'nativephp.version_code' => 5,
    ]);

    artisan('app:verify-release-version --skip-prompt')
        ->expectsOutputToContain('looks good')
        ->assertExitCode(0);
});
