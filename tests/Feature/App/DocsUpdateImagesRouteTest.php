<?php

function changelogImagesDirectory(): string
{
    return public_path('docs/updates/images');
}

it('keeps changelog images in the public docs directory', function () {
    $imagePath = changelogImagesDirectory().'/v-0-5-0/web/changelogs-modal.png';

    expect($imagePath)
        ->toBeFile()
        ->and(mime_content_type($imagePath))
        ->toBe('image/png');
});

it('keeps changelog image paths inside the public docs directory', function () {
    $imagesDirectory = realpath(changelogImagesDirectory());
    $requestedPath = realpath(changelogImagesDirectory().'/../changelogs.md');

    expect($imagesDirectory)->not->toBeFalse();
    expect($requestedPath)->not->toStartWith($imagesDirectory.DIRECTORY_SEPARATOR);
});

it('keeps changelog images available while running in native ios runtime', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'ios',
    ]);

    $imagePath = changelogImagesDirectory().'/v-0-4-0/android/responsive-text-overflow-handling.png';

    expect($imagePath)
        ->toBeFile()
        ->and(mime_content_type($imagePath))
        ->toBe('image/png');
});
