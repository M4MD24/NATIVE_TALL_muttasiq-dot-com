<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\WebHomeActivityChart;
use App\Http\Middleware\TrackWebHomeMetrics;
use App\Services\Monitoring\WebHomeActivityTracker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-12 10:15:00'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('applies web home metrics middleware to the home route', function () {
    $homeRoute = app('router')->getRoutes()->getByName('home');

    expect($homeRoute)->not->toBeNull()
        ->and($homeRoute?->gatherMiddleware())->toContain(TrackWebHomeMetrics::class)
        ->and($homeRoute?->gatherMiddleware())->not->toContain('App\Http\Middleware\SampleWebHomeRouteForNightwatch');
});

it('tracks home hits and approximate unique visitors for web requests', function () {
    config([
        'app.custom.security.web_home_metrics.enabled' => true,
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
});

it('skips web home metrics tracking when disabled', function () {
    config([
        'app.custom.security.web_home_metrics.enabled' => false,
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
});

it('skips web home metrics tracking for non-web platforms', function () {
    config([
        'app.custom.security.web_home_metrics.enabled' => true,
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

it('renders the custom dashboard with the web home activity chart widget', function () {
    $dashboard = app(Dashboard::class);

    $providerSource = file_get_contents(app_path('Providers/FilamentServiceProvider.php'));

    expect($dashboard->getWidgets())->toContain(WebHomeActivityChart::class)
        ->and($providerSource)->not->toBeFalse()
        ->and($providerSource)->toContain('Dashboard::class');
});
