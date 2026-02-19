<?php

declare(strict_types=1);

use App\Providers\FilamentServiceProvider;
use Filament\Support\Colors\Color;

use function Pest\Laravel\get;

it('builds filament background css variables from configured background colors', function () {
    config()->set('app.custom.filament.background_colors', [
        'shell' => [
            'light' => '#111111',
            'dark' => '#222222',
        ],
        'surface' => [
            'light' => '#333333',
            'dark' => '#444444',
        ],
        'surface_raised' => [
            'light' => '#555555',
            'dark' => '#666666',
        ],
        'surface_muted' => [
            'light' => '#777777',
            'dark' => '#888888',
        ],
    ]);

    $provider = new FilamentServiceProvider(app());

    $variables = (fn (): array => $this->filamentBackgroundCssVariables())
        ->call($provider);

    expect($variables)->toBe([
        'fi-shell-bg-light' => '#111111',
        'fi-shell-bg-dark' => '#222222',
        'fi-surface-bg-light' => '#333333',
        'fi-surface-bg-dark' => '#444444',
        'fi-surface-raised-bg-light' => '#555555',
        'fi-surface-raised-bg-dark' => '#666666',
        'fi-surface-muted-bg-light' => '#777777',
        'fi-surface-muted-bg-dark' => '#888888',
    ]);
});

it('falls back to surface colors when raised colors are not configured', function () {
    config()->set('app.custom.filament.background_colors', [
        'shell' => [
            'light' => '#aaaaaa',
            'dark' => '#bbbbbb',
        ],
        'surface' => [
            'light' => '#cccccc',
            'dark' => '#dddddd',
        ],
        'surface_raised' => [
            'light' => null,
            'dark' => null,
        ],
        'surface_muted' => [
            'light' => '#eeeeee',
            'dark' => '#ffffff',
        ],
    ]);

    $provider = new FilamentServiceProvider(app());

    $variables = (fn (): array => $this->filamentBackgroundCssVariables())
        ->call($provider);

    expect($variables['fi-surface-raised-bg-light'])->toBe('#cccccc');
    expect($variables['fi-surface-raised-bg-dark'])->toBe('#dddddd');
});

it('builds dark primary css variables from the configured filament override', function () {
    config()->set('app.custom.filament.color_overrides.primary.dark', '#5ea9bd');

    $provider = new FilamentServiceProvider(app());

    $variables = (fn (): array => $this->filamentCssVariables())
        ->call($provider);

    $expectedPalette = Color::generatePalette('#5ea9bd');

    expect($variables)->toHaveKeys([
        'fi-primary-dark-50',
        'fi-primary-dark-500',
        'fi-primary-dark-950',
    ]);
    expect($variables['fi-primary-dark-500'])->toBe(Color::convertToOklch((string) $expectedPalette[500]));
});

it('renders registered filament css variables in the app layout', function () {
    config()->set('app.custom.filament.background_colors', [
        'shell' => [
            'light' => '#121212',
            'dark' => '#232323',
        ],
        'surface' => [
            'light' => '#343434',
            'dark' => '#454545',
        ],
        'surface_raised' => [
            'light' => '#565656',
            'dark' => '#676767',
        ],
        'surface_muted' => [
            'light' => '#787878',
            'dark' => '#898989',
        ],
    ]);

    $response = get('/');

    $response->assertSuccessful();
    $response->assertSee('--fi-shell-bg-light:', false);
    $response->assertSee('--fi-surface-raised-bg-dark:', false);
    $response->assertSee('--fi-primary-dark-500:', false);
});
