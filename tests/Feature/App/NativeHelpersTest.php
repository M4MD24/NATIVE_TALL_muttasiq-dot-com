<?php

declare(strict_types=1);

it('returns a safe browser-open expression for mobile runtime', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'ios',
    ]);

    $expression = open_link_native_aware('https://example.com');

    expect($expression)
        ->toContain('window.browser?.open')
        ->toContain('window.open(`https://example.com`, `_blank`, `noopener`)');
});

it('returns a window-open expression for desktop runtime', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'desktop',
    ]);

    expect(open_link_native_aware('https://example.com'))
        ->toBe('window.open(`https://example.com`, `_blank`, `noopener`)');
});
