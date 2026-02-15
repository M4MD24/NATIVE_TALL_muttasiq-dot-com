@php
    $resolveFilamentBackground = static fn ($value, string $fallback): string => is_string($value) && $value !== '' ? $value : $fallback;

    $fiShellBackgroundLight = $resolveFilamentBackground(
        config('app.custom.filament.background_colors.shell.light'),
        theme_color('background', '#fef7ff'),
    );
    $fiShellBackgroundDark = $resolveFilamentBackground(
        config('app.custom.filament.background_colors.shell.dark'),
        theme_color('background-dark', '#201f25'),
    );

    $fiSurfaceBackgroundLight = $resolveFilamentBackground(
        config('app.custom.filament.background_colors.surface.light'),
        $fiShellBackgroundLight,
    );
    $fiSurfaceBackgroundDark = $resolveFilamentBackground(
        config('app.custom.filament.background_colors.surface.dark'),
        $fiShellBackgroundDark,
    );

    $fiSurfaceMutedBackgroundLight = $resolveFilamentBackground(
        config('app.custom.filament.background_colors.surface_muted.light'),
        $fiShellBackgroundLight,
    );
    $fiSurfaceMutedBackgroundDark = $resolveFilamentBackground(
        config('app.custom.filament.background_colors.surface_muted.dark'),
        $fiShellBackgroundDark,
    );
@endphp

<style>
    :root {
        --fi-shell-bg-light: {{ $fiShellBackgroundLight }};
        --fi-shell-bg-dark: {{ $fiShellBackgroundDark }};
        --fi-surface-bg-light: {{ $fiSurfaceBackgroundLight }};
        --fi-surface-bg-dark: {{ $fiSurfaceBackgroundDark }};
        --fi-surface-muted-bg-light: {{ $fiSurfaceMutedBackgroundLight }};
        --fi-surface-muted-bg-dark: {{ $fiSurfaceMutedBackgroundDark }};
    }
</style>
