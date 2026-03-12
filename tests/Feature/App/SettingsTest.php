<?php

use App\Livewire\ControlPanel;
use App\Models\Setting;
use App\Providers\AppServiceProvider;
use App\Services\Traits\HasControlPanelAboutTab;
use App\Services\Traits\HasControlPanelChangelogsTab;
use App\Services\Traits\HasControlPanelSettingsTab;

use function Pest\Livewire\livewire;

it('composes the control panel from tab-specific traits', function () {
    $usedTraits = class_uses_recursive(ControlPanel::class);

    expect($usedTraits)
        ->toContain(HasControlPanelSettingsTab::class)
        ->toContain(HasControlPanelChangelogsTab::class)
        ->toContain(HasControlPanelAboutTab::class);
});

it('does not persist settings changes globally', function () {
    Setting::query()->firstOrCreate(
        ['name' => Setting::DOES_SKIP_GUIDANCE_PANELS],
        ['value' => false],
    );

    $initialSettings = Setting::query()->pluck('value', 'name')->all();

    $payload = [
        Setting::DOES_AUTOMATICALLY_SWITCH_COMPLETED_ATHKAR => false,
        Setting::DOES_CLICKING_SWITCH_ATHKAR_TOO => false,
        Setting::DOES_PREVENT_SWITCHING_ATHKAR_UNTIL_COMPLETION => false,
        Setting::DOES_SKIP_GUIDANCE_PANELS => true,
        Setting::DOES_ENABLE_VISUAL_ENHANCEMENTS => false,
        Setting::MINIMUM_MAIN_TEXT_SIZE => 18,
        Setting::MAXIMUM_MAIN_TEXT_SIZE => 20,
    ];

    livewire(ControlPanel::class)
        ->callAction('controlPanel', data: $payload)
        ->assertHasNoFormErrors()
        ->assertDispatched('control-panel-updated');

    $updatedSettings = Setting::query()->pluck('value', 'name')->all();

    expect($updatedSettings)->toBe($initialSettings);
});

it('resolves the app version from stored settings when available', function () {
    Setting::setAppVersion('2.0.0');

    expect(Setting::appVersion())->toBe('2.0.0');
});

it('falls back to config when no app version setting is stored', function () {
    Setting::query()->where('name', Setting::APP_VERSION)->delete();
    config(['app.custom.app_version' => '9.9.9']);

    expect(Setting::appVersion())->toBe('9.9.9');
});

it('normalizes the main text size range in the settings modal', function () {
    livewire(ControlPanel::class)
        ->callAction('controlPanel', data: [
            'main_text_size_range' => [
                Setting::MIN_MAIN_TEXT_SIZE_MIN - 1,
                Setting::MAX_MAIN_TEXT_SIZE_MAX + 1,
            ],
        ])
        ->assertHasFormErrors(['main_text_size_range.0', 'main_text_size_range.1']);

    livewire(ControlPanel::class)
        ->callAction('controlPanel', data: [
            'main_text_size_range' => [19, 16],
        ])
        ->assertHasNoFormErrors()
        ->assertSet('clientControlPanel.minimum_main_text_size', 16)
        ->assertSet('clientControlPanel.maximum_main_text_size', 19);
});

it('accepts a valid main text size range in the settings modal', function () {
    livewire(ControlPanel::class)
        ->callAction('controlPanel', data: [
            'main_text_size_range' => [14, 19],
        ])
        ->assertHasNoFormErrors()
        ->assertDispatched('control-panel-updated');
});

it('can run a silent reader maintenance pulse through the control panel action lifecycle', function () {
    livewire(ControlPanel::class)
        ->call('triggerReaderMaintenancePulse')
        ->assertSet('mountedActions', [])
        ->assertDispatched('control-panel-updated');
});

it('keeps changelog image urls renderable when running in native ios runtime', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'ios',
    ]);

    $provider = app()->getProvider(AppServiceProvider::class);
    expect($provider)->not->toBeNull();

    $provider->boot();

    $component = app(ControlPanel::class);
    $method = new ReflectionMethod($component, 'changelogsMarkdown');
    $method->setAccessible(true);

    $html = $method->invoke($component)->toHtml();

    expect($html)
        ->toContain('src="/_assets/docs/updates/images/')
        ->not->toContain('src="data:image/png;base64,')
        ->not->toContain('src="php://127.0.0.1/docs/updates/images/');
});

it('keeps changelog image urls as public paths outside native ios runtime', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
    ]);

    $component = app(ControlPanel::class);
    $method = new ReflectionMethod($component, 'changelogsMarkdown');
    $method->setAccessible(true);

    $html = $method->invoke($component)->toHtml();

    expect($html)
        ->toContain('src="/_assets/docs/updates/images/')
        ->not->toContain('src="/docs/updates/image-proxy/')
        ->not->toContain('src="data:image/png;base64,');
});

it('keeps changelog image urls as relative public paths outside native runtime', function () {
    config([
        'nativephp-internal.running' => false,
    ]);

    $component = app(ControlPanel::class);
    $method = new ReflectionMethod($component, 'changelogsMarkdown');
    $method->setAccessible(true);

    $html = $method->invoke($component)->toHtml();

    expect($html)
        ->toContain('src="/docs/updates/images/')
        ->not->toContain('src="/_assets/docs/updates/images/');
});
