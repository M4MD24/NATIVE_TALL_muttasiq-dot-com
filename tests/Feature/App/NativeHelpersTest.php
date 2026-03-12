<?php

declare(strict_types=1);

it('returns correct link-open expressions for mobile and desktop runtimes', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'ios',
    ]);

    $expression = open_link_native_aware('https://example.com');

    expect($expression)
        ->toContain('window.browser?.open')
        ->toContain('window.open(`https://example.com`, `_blank`, `noopener`)');

    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'desktop',
    ]);

    expect(open_link_native_aware('https://example.com'))
        ->toBe('window.open(`https://example.com`, `_blank`, `noopener`)');
});

it('detects platform flags for web and native contexts', function () {
    config([
        'nativephp-internal.running' => false,
        'nativephp-internal.platform' => null,
    ]);

    expect(is_platform('web'))->toBeTrue()
        ->and(is_platform('native'))->toBeFalse()
        ->and(is_platform('desktop'))->toBeFalse()
        ->and(is_platform('mobile'))->toBeFalse();

    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
    ]);

    expect(is_platform('web'))->toBeFalse()
        ->and(is_platform('native'))->toBeTrue()
        ->and(is_platform('mobile'))->toBeTrue()
        ->and(is_platform('android'))->toBeTrue();
});
