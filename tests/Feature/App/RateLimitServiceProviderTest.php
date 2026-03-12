<?php

use App\Providers\RateLimitServiceProvider;
use Illuminate\Support\Facades\RateLimiter;

it('registers application rate limiters only through the dedicated provider', function () {
    $provider = app()->getProvider(RateLimitServiceProvider::class);

    expect($provider)->not->toBeNull();

    $provider->boot();

    expect(RateLimiter::limiter('athkar'))->toBeCallable()
        ->and(RateLimiter::limiter('settings'))->toBeCallable()
        ->and(RateLimiter::limiter('js-error-reports'))->toBeCallable();

    $appProviderSource = file_get_contents(app_path('Providers/AppServiceProvider.php'));
    $athkarProviderSource = file_get_contents(app_path('Providers/AthkarAppServiceProvider.php'));

    expect($appProviderSource)->not->toBeFalse()
        ->and($athkarProviderSource)->not->toBeFalse()
        ->and($appProviderSource)->not->toContain('RateLimiter::for(')
        ->and($athkarProviderSource)->not->toContain('RateLimiter::for(');
});
