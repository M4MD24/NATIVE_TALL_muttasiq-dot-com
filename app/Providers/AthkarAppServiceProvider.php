<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Thikr;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\EloquentSortable\EloquentModelSortedEvent;

class AthkarAppServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerAthkarRateLimiter();
        $this->registerAthkarCacheListeners();
    }

    private function registerAthkarRateLimiter(): void
    {
        RateLimiter::for('athkar', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function registerAthkarCacheListeners(): void
    {
        Event::listen(EloquentModelSortedEvent::class, function (EloquentModelSortedEvent $event): void {
            if (! $event->isFor(Thikr::class)) {
                return;
            }

            Thikr::clearDefaultCache();
        });
    }
}
