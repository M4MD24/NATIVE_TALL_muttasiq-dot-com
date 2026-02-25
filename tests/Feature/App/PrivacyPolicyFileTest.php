<?php

declare(strict_types=1);

it('ships a public privacy policy file for store listings', function () {
    $privacyPolicyPath = public_path('policies/privacy.txt');

    expect(is_file($privacyPolicyPath))->toBeTrue();

    $contents = file_get_contents($privacyPolicyPath);

    expect($contents)
        ->toBeString()
        ->toContain('Muttasiq Privacy Policy')
        ->toContain('/policies/privacy.txt');
});
