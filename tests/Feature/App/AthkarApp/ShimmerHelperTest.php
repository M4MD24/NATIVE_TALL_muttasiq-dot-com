<?php

declare(strict_types=1);

it('uses the shared athkar-agnostic shimmer helper from the reader script', function () {
    $source = file_get_contents(resource_path('js/support/alpine/data/athkar-app-reader.js'));

    expect($source)->not->toBeFalse()
        ->and($source)->toContain("import { createShimmerController } from '../shimmer';")
        ->and($source)->toContain('createShimmerController({')
        ->and($source)->not->toContain('createAthkarShimmerController');

    $source = file_get_contents(resource_path('js/support/alpine/shimmer.js'));

    expect($source)->not->toBeFalse()
        ->and($source)->toContain('export const createShimmerController')
        ->and($source)->not->toContain('athkar-')
        ->and($source)->not->toContain('createAthkarShimmerController');
});
