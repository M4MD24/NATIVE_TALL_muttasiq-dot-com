<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Thikr;
use Illuminate\Support\Facades\Event;
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
        $this->clearAthkarCacheUponReorder();
    }

    private function clearAthkarCacheUponReorder(): void
    {
        Event::listen(EloquentModelSortedEvent::class, function (EloquentModelSortedEvent $event): void {
            if (! $event->isFor(Thikr::class)) {
                return;
            }

            Thikr::clearDefaultCache();
        });
    }
}
