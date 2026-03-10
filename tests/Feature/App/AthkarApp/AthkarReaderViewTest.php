<?php

declare(strict_types=1);

it('renders the manager button hover treatment in the reader view', function () {
    $source = file_get_contents(resource_path('views/components/partials/athkar-app/reader.blade.php'));

    expect($source)->not->toBeFalse()
        ->and($source)->toContain('athkar-chip--manager')
        ->and($source)->toContain('data-athkar-open-manager')
        ->and($source)->toContain('--athkar-manager-button-fill-start: color-mix(in srgb, var(--primary-600)')
        ->and($source)->toContain('--athkar-manager-button-fill-start: color-mix(in srgb, var(--primary-100)')
        ->and($source)->toContain('--athkar-manager-button-fill-end: color-mix(in srgb, var(--primary-50)')
        ->and($source)->toContain('--athkar-manager-button-bevel:')
        ->and($source)->toContain('--athkar-manager-button-text: var(--primary-600)')
        ->and($source)->toContain('inset 0 0 0 1px var(--athkar-manager-button-bevel),')
        ->and($source)->toContain('.athkar-chip--manager::after')
        ->and($source)->toContain('transition: opacity 220ms ease;')
        ->and($source)->toContain('.athkar-chip--manager:hover::after')
        ->and($source)->toContain('animation: athkar-manager-sheen 500ms linear;');
});
