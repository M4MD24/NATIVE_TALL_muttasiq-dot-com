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

it('enforces the main text size bounds in the settings modal', function () {
    livewire(Settings::class)
        ->callAction('settings', data: [
            'minimum_main_text_size' => 9,
            'maximum_main_text_size' => 20,
        ])
        ->assertHasFormErrors(['minimum_main_text_size']);

    livewire(Settings::class)
        ->callAction('settings', data: [
            'minimum_main_text_size' => 21,
            'maximum_main_text_size' => 20,
        ])
        ->assertHasFormErrors(['minimum_main_text_size']);

    livewire(Settings::class)
        ->callAction('settings', data: [
            'minimum_main_text_size' => 16,
            'maximum_main_text_size' => 9,
        ])
        ->assertHasFormErrors(['maximum_main_text_size']);

    livewire(Settings::class)
        ->callAction('settings', data: [
            'minimum_main_text_size' => 16,
            'maximum_main_text_size' => 21,
        ])
        ->assertHasFormErrors(['maximum_main_text_size']);
});

it('requires maximum_main_text_size to be greater than or equal to minimum_main_text_size', function () {
    livewire(Settings::class)
        ->callAction('settings', data: [
            'minimum_main_text_size' => 19,
            'maximum_main_text_size' => 16,
        ])
        ->assertHasFormErrors(['minimum_main_text_size', 'maximum_main_text_size']);
});
