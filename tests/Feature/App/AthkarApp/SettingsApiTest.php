<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\getJson;

it('returns current settings and main text size limits', function () {
    RateLimiter::for('settings', fn (Request $request): Limit => Limit::none());

    Setting::query()->updateOrCreate(
        ['name' => Setting::DOES_SKIP_GUIDANCE_PANELS],
        ['value' => 1],
    );

    $response = getJson(route('api.settings.index'));

    $response->assertSuccessful();

    $settings = $response->json('settings');

    expect($settings)
        ->toBeArray()
        ->toHaveKey(Setting::DOES_SKIP_GUIDANCE_PANELS, true)
        ->toHaveKey(Setting::DOES_ENABLE_MAIN_TEXT_SHIMMERING, true)
        ->toHaveKey(Setting::MINIMUM_MAIN_TEXT_SIZE)
        ->toHaveKey(Setting::MAXIMUM_MAIN_TEXT_SIZE);

    $limits = $response->json('mainTextSizeLimits');

    expect($limits)
        ->toBeArray()
        ->toHaveKey(Setting::MINIMUM_MAIN_TEXT_SIZE)
        ->toHaveKey(Setting::MAXIMUM_MAIN_TEXT_SIZE);

    expect($limits[Setting::MINIMUM_MAIN_TEXT_SIZE])
        ->toHaveKeys(['min', 'max', 'default']);
});

it('returns normalized settings from the database', function () {
    RateLimiter::for('settings', fn (Request $request): Limit => Limit::none());

    Setting::query()->updateOrCreate(
        ['name' => Setting::MINIMUM_MAIN_TEXT_SIZE],
        ['value' => 18],
    );

    Setting::query()->updateOrCreate(
        ['name' => Setting::MAXIMUM_MAIN_TEXT_SIZE],
        ['value' => 20],
    );

    $response = getJson(route('api.settings.index'));

    $response->assertSuccessful();

    expect($response->json('settings.'.Setting::MINIMUM_MAIN_TEXT_SIZE))->toBe(18);
    expect($response->json('settings.'.Setting::MAXIMUM_MAIN_TEXT_SIZE))->toBe(20);
});

it('returns persisted shimmer setting from the database', function () {
    RateLimiter::for('settings', fn (Request $request): Limit => Limit::none());

    Setting::query()->updateOrCreate(
        ['name' => Setting::DOES_ENABLE_MAIN_TEXT_SHIMMERING],
        ['value' => 0],
    );

    $response = getJson(route('api.settings.index'));

    $response->assertSuccessful();

    expect($response->json('settings.'.Setting::DOES_ENABLE_MAIN_TEXT_SHIMMERING))->toBeFalse();
});

it('rate limits the settings endpoint', function () {
    RateLimiter::for('settings', fn (Request $request): Limit => Limit::perMinute(2)->by('test'));

    getJson(route('api.settings.index'))->assertSuccessful();
    getJson(route('api.settings.index'))->assertSuccessful();
    getJson(route('api.settings.index'))->assertTooManyRequests();
});
