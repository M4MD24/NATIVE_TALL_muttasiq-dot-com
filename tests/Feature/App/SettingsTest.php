<?php

use App\Livewire\ControlPanel;
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

    livewire(ControlPanel::class)
        ->callAction('controlPanel', data: $payload)
        ->assertHasNoFormErrors()
        ->assertDispatched('control-panel-updated');

    $updatedSettings = Setting::query()->pluck('value', 'name')->all();

    expect($updatedSettings)->toBe($initialSettings);
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
