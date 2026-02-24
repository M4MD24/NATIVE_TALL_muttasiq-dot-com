<?php

declare(strict_types=1);

use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

it('allows the admin to access the manage settings page', function () {
    config(['app.custom.user.email' => 'admin@example.test']);

    $admin = User::factory()->create(['email' => 'admin@example.test']);

    actingAs($admin);

    get(route('filament.admin.pages.iedadat-iftiradiyya'))->assertSuccessful();
});

it('loads current settings into the form', function () {
    config(['app.custom.user.email' => 'admin@example.test']);

    $admin = User::factory()->create(['email' => 'admin@example.test']);

    Setting::query()->updateOrCreate(
        ['name' => 'does_skip_notice_panels'],
        ['value' => 1],
    );

    actingAs($admin);
    Filament::setCurrentPanel('admin');

    livewire(ManageSettings::class)
        ->assertFormSet([
            'does_skip_notice_panels' => true,
        ]);
});

it('saves settings to the database', function () {
    config(['app.custom.user.email' => 'admin@example.test']);

    $admin = User::factory()->create(['email' => 'admin@example.test']);

    actingAs($admin);
    Filament::setCurrentPanel('admin');

    livewire(ManageSettings::class)
        ->fillForm([
            'does_skip_notice_panels' => true,
            'does_automatically_switch_completed_athkar' => false,
            'minimum_main_text_size' => 18,
            'maximum_main_text_size' => 22,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::query()->where('name', 'does_skip_notice_panels')->value('value'))
        ->toBe(1);

    expect(Setting::query()->where('name', 'does_automatically_switch_completed_athkar')->value('value'))
        ->toBe(0);

    expect((int) Setting::query()->where('name', 'minimum_main_text_size')->value('value'))
        ->toBe(18);

    expect((int) Setting::query()->where('name', 'maximum_main_text_size')->value('value'))
        ->toBe(22);
});

it('normalizes min/max text size when saving with inverted values', function () {
    config(['app.custom.user.email' => 'admin@example.test']);

    $admin = User::factory()->create(['email' => 'admin@example.test']);

    actingAs($admin);
    Filament::setCurrentPanel('admin');

    livewire(ManageSettings::class)
        ->fillForm([
            'minimum_main_text_size' => 22,
            'maximum_main_text_size' => 18,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $min = (int) Setting::query()->where('name', 'minimum_main_text_size')->value('value');
    $max = (int) Setting::query()->where('name', 'maximum_main_text_size')->value('value');

    expect($min)->toBeLessThanOrEqual($max);
});
