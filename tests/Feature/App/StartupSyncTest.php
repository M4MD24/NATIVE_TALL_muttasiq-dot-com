<?php

use App\Livewire\StartupSync;
use App\Models\Setting;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('syncs app version from settings api on mobile startup', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
        'app.url' => 'http://muttasiq.dev.localhost',
        'app.custom.native_end_points.settings' => 'settings',
        'app.custom.native_end_points.retries' => 2,
    ]);

    Setting::setAppVersion('1.0.0');

    $expectedSettingsUrl = rtrim((string) config('app.url'), '/').route('api.settings.index', [], false);

    Http::fake([
        $expectedSettingsUrl => Http::response([
            'settings' => Setting::normalizeSettings(Setting::defaults()),
            'mainTextSizeLimits' => Setting::mainTextSizeLimits(),
            'appVersion' => '2.5.1',
        ]),
    ]);

    Livewire::test(StartupSync::class)
        ->assertDispatched('app-version-updated', version: '2.5.1')
        ->assertDispatched('startup-sync-finished');

    expect(Setting::appVersion())->toBe('2.5.1');

    Http::assertSent(function (HttpRequest $request) use ($expectedSettingsUrl): bool {
        return $request->url() === $expectedSettingsUrl;
    });
});

it('keeps existing app version when startup settings api omits app version', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
        'app.url' => 'http://muttasiq.dev.localhost',
        'app.custom.native_end_points.settings' => 'settings',
    ]);

    Setting::setAppVersion('3.1.4');

    $expectedSettingsUrl = rtrim((string) config('app.url'), '/').route('api.settings.index', [], false);

    Http::fake([
        $expectedSettingsUrl => Http::response([
            'settings' => Setting::normalizeSettings(Setting::defaults()),
            'mainTextSizeLimits' => Setting::mainTextSizeLimits(),
        ]),
    ]);

    Livewire::test(StartupSync::class)
        ->assertDispatched('app-version-updated', version: '3.1.4')
        ->assertDispatched('startup-sync-finished');

    expect(Setting::appVersion())->toBe('3.1.4');

    Http::assertSent(function (HttpRequest $request) use ($expectedSettingsUrl): bool {
        return $request->url() === $expectedSettingsUrl;
    });
});

it('uses configured absolute endpoint for startup settings sync', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
        'app.url' => 'php://127.0.0.1',
        'app.custom.native_end_points.settings' => 'https://muttasiq.com/api/settings',
    ]);

    Setting::setAppVersion('1.0.0');

    Http::fake([
        'https://muttasiq.com/api/settings' => Http::response([
            'settings' => Setting::normalizeSettings(Setting::defaults()),
            'mainTextSizeLimits' => Setting::mainTextSizeLimits(),
            'appVersion' => '9.9.9',
        ]),
    ]);

    Livewire::test(StartupSync::class)
        ->assertDispatched('app-version-updated', version: '9.9.9')
        ->assertDispatched('startup-sync-finished');

    expect(Setting::appVersion())->toBe('9.9.9');

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://muttasiq.com/api/settings';
    });
});

it('skips startup settings sync when app url is non-http and endpoint is relative', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
        'app.url' => 'php://127.0.0.1',
        'app.custom.native_end_points.settings' => 'settings',
    ]);

    Setting::setAppVersion('4.2.0');
    Http::fake();

    Livewire::test(StartupSync::class)
        ->assertDispatched('app-version-updated', version: '4.2.0')
        ->assertDispatched('startup-sync-finished');

    expect(Setting::appVersion())->toBe('4.2.0');
    Http::assertNothingSent();
});
