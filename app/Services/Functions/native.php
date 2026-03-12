<?php

declare(strict_types=1);

use Native\Mobile\Facades\System;

if (! function_exists('is_platform')) {
    function is_platform(string $platform): bool
    {
        $platform = strtolower($platform);

        $nativeRuntime = (bool) config('nativephp-internal.running', false);
        $nativePlatform = strtolower((string) config('nativephp-internal.platform', ''));

        if ($nativeRuntime && $nativePlatform === '') {
            if (System::isAndroid()) {
                $nativePlatform = 'android';
            } elseif (System::isIos()) {
                $nativePlatform = 'ios';
            }
        }

        $isAndroid = $nativePlatform === 'android';
        $isIos = $nativePlatform === 'ios';
        $isMobile = $isAndroid || $isIos;
        $isNative = $nativeRuntime || $isMobile;
        $isWeb = ! $isNative;
        $isDesktop = $isNative && ! $isMobile;

        return match ($platform) {
            'android' => $isAndroid,
            'ios' => $isIos,
            'mobile' => $isMobile,
            'native' => $isNative,
            'web' => $isWeb,
            'desktop' => $isDesktop,
            default => throw new InvalidArgumentException('Unrecognized platform.'),
        };
    }
} else {
    throw new Exception('The function `is_platform` already exists.');
}

if (! function_exists('open_link_native_aware')) {
    function open_link_native_aware(string $url): string
    {
        if (is_platform('mobile')) {
            return "(window.browser?.open ? await window.browser.open(`{$url}`) : window.open(`{$url}`, `_blank`, `noopener`))";
        }

        return "window.open(`{$url}`, `_blank`, `noopener`)";
    }
} else {
    throw new Exception('The function `open_link_native_aware` already exists.');
}
