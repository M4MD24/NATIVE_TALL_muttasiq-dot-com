<?php

use function Pest\Laravel\get;

it('serves changelog images from the public docs directory', function () {
    $response = get('/docs/updates/images/v-0-5-0/web/changelogs-modal.png');

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'image/png');
});

it('prevents directory traversal when serving changelog images', function () {
    get('/docs/updates/images/%2E%2E/%2E%2E/changelogs.md')
        ->assertNotFound();
});

it('serves changelog images while running in native ios runtime', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'ios',
    ]);

    get('/docs/updates/images/v-0-4-0/android/responsive-text-overflow-handling.png')
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/png');
});
