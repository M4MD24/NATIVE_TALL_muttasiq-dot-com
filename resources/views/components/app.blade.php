<!DOCTYPE html>
<html
    x-data
    lang="ar"
    dir="rtl"
>

<head>
    @include('partials.meta')
    @include('partials.favicon')
    @stack('meta')

    <!-- Fonts -->
    @php($scheherazadeFont = Vite::asset('resources/fonts/scheherazade-new/ScheherazadeNew-Regular.ttf'))
    <link
        type="font/ttf"
        href="{{ $scheherazadeFont }}"
        rel="preload"
        as="font"
        crossorigin
    >
    @stack('fonts')

    <!-- Styles -->
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
    @filamentStyles
    @lazyCss(['resources/css/core/filament/components.css', 'resources/css/app-lazy.css'])
    @vite('resources/css/app.css')
    @stack('styles')

    <!-- Head Scripts --> {{-- ! Loads only once --}}
    @vite('resources/js/app.js')
    @if (app()->isLocal())
        @if (is_platform('mobile'))
            @include('partials.scripts.js-errors-mobile-overlay')
        @endif
    @endif
    @stack('head-scripts')
</head>

<body
    class="{{ twMerge([
        'mobile-platform' => is_platform('mobile'),
        'nativephp-safe-area font-arabic-sans relative h-full min-h-dvh antialiased transition-[opacity,color,background-color,border-color,text-decoration-color,fill,stroke] ease-in will-change-[opacity,color,background-color,border-color,text-decoration-color,fill,stroke]',
    ]) }}"
    x-bind:class="{
        'overflow-hidden': isScrollingDisabled,
        'ease-out!': useFastTransitionDuration,
    }"
    x-on:toggle-scroller.window="isScrollingDisabled = $event.detail?.disabled ?? !isScrollingDisabled"
    x-bind:style="{
        backgroundColor: $store.colorScheme.bodyBackgroundColor,
        transitionDuration: (fastTransitionDurationInMs + 'ms'),
        opacity: (isBodyVisible ? 1 : 0),
    }"
    x-data="layoutManager()"
>
    @include('partials.blinker')

    <div class="flex min-h-[calc(100dvh-var(--inset-top,0px)-var(--inset-bottom,0px))] flex-col">
        {{ $slot }}
    </div>

    @livewire('notifications')

    <!-- Body Scripts --> {{-- ! Reloads on every refresh --}}
    @ineresh
    @filamentScripts
    @stack('body-scripts')
</body>

</html>
