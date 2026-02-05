<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();
        Livewire::useScriptTagAttributes(['defer' => true]);
        $this->disableViteHotFileWhenUnavailable();
    }

    private function disableViteHotFileWhenUnavailable(): void
    {
        if (! config('nativephp-internal.running')) {
            return;
        }

        $hotFile = Vite::hotFile();

        if (! is_file($hotFile)) {
            return;
        }

        $hotUrl = trim((string) file_get_contents($hotFile));

        if ($hotUrl === '') {
            return;
        }

        $host = parse_url($hotUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return;
        }

        $scheme = parse_url($hotUrl, PHP_URL_SCHEME) ?: 'http';
        $port = parse_url($hotUrl, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80);

        $timeoutSeconds = 0.2;
        $connection = @fsockopen($host, (int) $port, $errno, $error, $timeoutSeconds);

        if (is_resource($connection)) {
            fclose($connection);

            return;
        }

        Vite::useHotFile(storage_path('framework/vite.hot.disabled'));
    }
}
