<?php

use App\Livewire\Settings;
use App\Models\Setting;

use function Pest\Livewire\livewire;

it('does not persist settings changes globally', function () {
    Setting::query()->firstOrCreate(
        ['name' => 'does_skip_notice_panels'],
        ['value' => false],
    );

    $initialSettings = Setting::query()->pluck('value', 'name')->all();

    $payload = [
        'does_automatically_switch_completed_athkar' => false,
        'does_clicking_switch_athkar_too' => false,
        'does_prevent_switching_athkar_until_completion' => false,
        'does_skip_notice_panels' => true,
        'minimum_main_text_size' => 18,
        'maximum_main_text_size' => 20,
    ];

    livewire(Settings::class)
        ->callAction('settings', data: $payload)
        ->assertHasNoFormErrors()
        ->assertDispatched('settings-updated');

    $updatedSettings = Setting::query()->pluck('value', 'name')->all();

    expect($updatedSettings)->toBe($initialSettings);
});

it('normalizes the main text size range in the settings modal', function () {
    livewire(Settings::class)
        ->callAction('settings', data: [
            'main_text_size_range' => [9, 21],
        ])
        ->assertHasFormErrors(['main_text_size_range.0', 'main_text_size_range.1']);

    livewire(Settings::class)
        ->callAction('settings', data: [
            'main_text_size_range' => [19, 16],
        ])
        ->assertHasNoFormErrors()
        ->assertSet('clientSettings.minimum_main_text_size', 16)
        ->assertSet('clientSettings.maximum_main_text_size', 19);
});

it('accepts a valid main text size range in the settings modal', function () {
    livewire(Settings::class)
        ->callAction('settings', data: [
            'main_text_size_range' => [12, 19],
        ])
        ->assertHasNoFormErrors()
        ->assertDispatched('settings-updated');
});
