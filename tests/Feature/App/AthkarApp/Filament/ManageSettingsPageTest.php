<?php

declare(strict_types=1);

use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

it('allows the admin to access manage settings and loads the current settings form state', function () {
    config(['app.custom.user.email' => 'admin@example.test']);

    $admin = User::factory()->create(['email' => 'admin@example.test']);

    actingAs($admin);

    get(route('filament.admin.pages.iedadat-iftiradiyya'))->assertSuccessful();

    Setting::query()->updateOrCreate(
        ['name' => Setting::DOES_SKIP_GUIDANCE_PANELS],
        ['value' => 1],
    );
    Setting::query()->updateOrCreate(
        ['name' => Setting::DOES_ENABLE_VISUAL_ENHANCEMENTS],
        ['value' => 0],
    );
    Setting::query()->updateOrCreate(
        ['name' => Setting::APP_VERSION],
        ['value' => 0, 'value_text' => '2.5.1'],
    );

    actingAs($admin);
    Filament::setCurrentPanel('admin');

    livewire(ManageSettings::class)
        ->assertFormSet([
            Setting::DOES_SKIP_GUIDANCE_PANELS => true,
            Setting::DOES_ENABLE_VISUAL_ENHANCEMENTS => false,
            Setting::APP_VERSION => '2.5.1',
        ]);
});

it('saves settings and normalizes inverted min/max text-size values', function () {
    config(['app.custom.user.email' => 'admin@example.test']);

    $admin = User::factory()->create(['email' => 'admin@example.test']);

    actingAs($admin);
    Filament::setCurrentPanel('admin');

    livewire(ManageSettings::class)
        ->fillForm([
            Setting::APP_VERSION => '3.0.0',
            Setting::DOES_SKIP_GUIDANCE_PANELS => true,
            Setting::DOES_ENABLE_VISUAL_ENHANCEMENTS => false,
            Setting::DOES_AUTOMATICALLY_SWITCH_COMPLETED_ATHKAR => false,
            Setting::MINIMUM_MAIN_TEXT_SIZE => 18,
            Setting::MAXIMUM_MAIN_TEXT_SIZE => 22,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::query()->where('name', Setting::DOES_SKIP_GUIDANCE_PANELS)->value('value'))
        ->toBe(1);

    expect(Setting::query()->where('name', Setting::DOES_AUTOMATICALLY_SWITCH_COMPLETED_ATHKAR)->value('value'))
        ->toBe(0);

    expect(Setting::query()->where('name', Setting::DOES_ENABLE_VISUAL_ENHANCEMENTS)->value('value'))
        ->toBe(0);

    expect(Setting::query()->where('name', Setting::APP_VERSION)->value('value_text'))
        ->toBe('3.0.0');

    expect((int) Setting::query()->where('name', Setting::MINIMUM_MAIN_TEXT_SIZE)->value('value'))
        ->toBe(18);

    expect((int) Setting::query()->where('name', Setting::MAXIMUM_MAIN_TEXT_SIZE)->value('value'))
        ->toBe(22);

    livewire(ManageSettings::class)
        ->fillForm([
            Setting::MINIMUM_MAIN_TEXT_SIZE => 22,
            Setting::MAXIMUM_MAIN_TEXT_SIZE => 18,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $min = (int) Setting::query()->where('name', Setting::MINIMUM_MAIN_TEXT_SIZE)->value('value');
    $max = (int) Setting::query()->where('name', Setting::MAXIMUM_MAIN_TEXT_SIZE)->value('value');

    expect($min)->toBeLessThanOrEqual($max);
});
