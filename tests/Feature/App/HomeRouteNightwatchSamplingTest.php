<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\WebHomeActivityChart;
use App\Http\Middleware\TrackWebHomeMetrics;
use App\Services\Monitoring\WebHomeActivityTracker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config([
        'app.custom.security.web_home_metrics.cache_store' => 'array',
    ]);
    Cache::store('array')->flush();
    Cache::flush();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-12 10:15:00'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('wires home route/dashboard to web home metrics middleware and widget', function () {
    $homeRoute = app('router')->getRoutes()->getByName('home');

    expect($homeRoute)->not->toBeNull()
        ->and($homeRoute?->gatherMiddleware())->toContain(TrackWebHomeMetrics::class)
        ->and($homeRoute?->gatherMiddleware())->not->toContain('App\Http\Middleware\SampleWebHomeRouteForNightwatch');
    $dashboard = app(Dashboard::class);
    $providerSource = file_get_contents(app_path('Providers/FilamentServiceProvider.php'));

    expect($dashboard->getWidgets())->toContain(WebHomeActivityChart::class)
        ->and($providerSource)->not->toBeFalse()
        ->and($providerSource)->toContain('Dashboard::class');
});

it('tracks hits and unique visitors for web requests when metrics are enabled', function () {
    config([
        'app.custom.security.web_home_metrics.enabled' => true,
        'app.custom.security.web_home_metrics.cache_store' => 'array',
        'nativephp-internal.running' => false,
        'nativephp-internal.platform' => null,
    ]);

    $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => 'Agent A',
    ])->get('/')->assertSuccessful();

    $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => 'Agent A',
    ])->get('/')->assertSuccessful();

    $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.11',
        'HTTP_USER_AGENT' => 'Agent B',
    ])->get('/')->assertSuccessful();

    $tracker = app(WebHomeActivityTracker::class);
    $today = $tracker->todaySummary();
    $last24Hours = $tracker->last24HoursSummary();
    $series = $tracker->dailySeries(days: 1);

    expect($today)->toBe([
        'hits' => 3,
        'unique_visitors' => 2,
    ])->and($last24Hours)->toBe([
        'hits' => 3,
        'unique_visitors' => 2,
    ])->and($series['hits'])->toBe([3])
        ->and($series['unique_visitors'])->toBe([2]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-12 10:17:30'));

    expect($tracker->todaySummary())->toBe([
        'hits' => 3,
        'unique_visitors' => 2,
    ]);
});

it('skips web home metrics tracking when disabled or when request platform is non-web', function () {
    config([
        'app.custom.security.web_home_metrics.enabled' => false,
        'app.custom.security.web_home_metrics.cache_store' => 'array',
        'nativephp-internal.running' => false,
        'nativephp-internal.platform' => null,
    ]);

    $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.20',
        'HTTP_USER_AGENT' => 'Agent A',
    ])->get('/')->assertSuccessful();

    expect(app(WebHomeActivityTracker::class)->todaySummary())->toBe([
        'hits' => 0,
        'unique_visitors' => 0,
    ]);

    config([
        'app.custom.security.web_home_metrics.enabled' => true,
        'app.custom.security.web_home_metrics.cache_store' => 'array',
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
    ]);

    $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.20',
        'HTTP_USER_AGENT' => 'Agent A',
    ])->get('/')->assertSuccessful();

    expect(app(WebHomeActivityTracker::class)->todaySummary())->toBe([
        'hits' => 0,
        'unique_visitors' => 0,
    ]);
});

it('persists web home metrics in the configured cache store even when default cache is ephemeral', function () {
    $metricsCachePath = storage_path('framework/cache/web-home-metrics-tests');
    if (! is_dir($metricsCachePath)) {
        mkdir($metricsCachePath, 0777, true);
    }

    config([
        'cache.default' => 'array',
        'cache.stores.file.path' => $metricsCachePath,
        'cache.stores.file.lock_path' => $metricsCachePath,
        'app.custom.security.web_home_metrics.enabled' => true,
        'app.custom.security.web_home_metrics.cache_store' => 'file',
        'nativephp-internal.running' => false,
        'nativephp-internal.platform' => null,
    ]);

    Cache::store('file')->flush();

    $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.40',
        'HTTP_USER_AGENT' => 'Agent C',
    ])->get('/')->assertSuccessful();

    expect(app(WebHomeActivityTracker::class)->todaySummary())->toBe([
        'hits' => 1,
        'unique_visitors' => 1,
    ]);

    $this->refreshApplication();

    config([
        'cache.default' => 'array',
        'cache.stores.file.path' => $metricsCachePath,
        'cache.stores.file.lock_path' => $metricsCachePath,
        'app.custom.security.web_home_metrics.cache_store' => 'file',
    ]);

    expect(app(WebHomeActivityTracker::class)->todaySummary())->toBe([
        'hits' => 1,
        'unique_visitors' => 1,
    ]);

    Cache::store('file')->flush();
});
