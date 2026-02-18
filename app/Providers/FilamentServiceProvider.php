<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Overrides\Pages\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Livewire\Notifications;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FilamentServiceProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path(config('app.custom.admin_path'))
            ->login(Login::class)
            ->homeUrl(config('app.url'))
            ->brandLogo(sprintf('%s/images/logo-wide.svg', rtrim((string) config('app.url'), '/')))
            ->brandLogoHeight('5rem')
            ->colors(config('app.custom.colors'))
            ->font('Readex Pro')
            ->viteTheme('resources/css/core/filament/panels.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        FilamentColor::register(config('app.custom.colors'));
        FilamentAsset::registerCssVariables($this->filamentBackgroundCssVariables());

        Notifications::alignment(Alignment::End);
        Notifications::verticalAlignment(VerticalAlignment::End);

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => Blade::render('@ineresh'),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function filamentBackgroundCssVariables(): array
    {
        $shellLight = $this->resolveFilamentBackground(
            key: 'shell.light',
            fallback: $this->themeBackgroundFallback(name: 'background', fallback: '#fef7ff'),
        );
        $shellDark = $this->resolveFilamentBackground(
            key: 'shell.dark',
            fallback: $this->themeBackgroundFallback(name: 'background-dark', fallback: '#201f25'),
        );
        $surfaceLight = $this->resolveFilamentBackground(
            key: 'surface.light',
            fallback: $this->themeBackgroundFallback(name: 'gray-50', fallback: '#f9fafb'),
        );
        $surfaceDark = $this->resolveFilamentBackground(
            key: 'surface.dark',
            fallback: $this->themeBackgroundFallback(name: 'gray-900', fallback: '#111827'),
        );
        $surfaceRaisedLight = $this->resolveFilamentBackground(
            key: 'surface_raised.light',
            fallback: $surfaceLight,
        );
        $surfaceRaisedDark = $this->resolveFilamentBackground(
            key: 'surface_raised.dark',
            fallback: $surfaceDark,
        );

        return [
            'fi-shell-bg-light' => $shellLight,
            'fi-shell-bg-dark' => $shellDark,
            'fi-surface-bg-light' => $surfaceLight,
            'fi-surface-bg-dark' => $surfaceDark,
            'fi-surface-raised-bg-light' => $surfaceRaisedLight,
            'fi-surface-raised-bg-dark' => $surfaceRaisedDark,
            'fi-surface-muted-bg-light' => $this->resolveFilamentBackground('surface_muted.light', $surfaceLight),
            'fi-surface-muted-bg-dark' => $this->resolveFilamentBackground('surface_muted.dark', $surfaceDark),
        ];
    }

    protected function resolveFilamentBackground(string $key, string $fallback): string
    {
        $background = config("app.custom.filament.background_colors.{$key}");

        return is_string($background) && $background !== '' ? $background : $fallback;
    }

    protected function themeBackgroundFallback(string $name, string $fallback): string
    {
        if (! function_exists('theme_color')) {
            return $fallback;
        }

        try {
            return theme_color($name, $fallback);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
