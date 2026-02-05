<?php

declare(strict_types=1);

use Native\Mobile\Facades\System;

if (! function_exists('is_platform')) {
    function is_platform(string $platform): bool
    {
        $platform = strtolower($platform);

        if (config('nativephp-internal.running')) {
            $nativePlatform = strtolower((string) config('nativephp-internal.platform', ''));

            if ($nativePlatform !== '') {
                return match ($platform) {
                    'android' => $nativePlatform === 'android',
                    'ios' => $nativePlatform === 'ios',
                    'mobile' => in_array($nativePlatform, ['android', 'ios'], true),
                    'desktop' => ! in_array($nativePlatform, ['android', 'ios'], true),
                    default => throw new InvalidArgumentException('Unrecognized platform.'),
                };
            }
        }

        $isAndroid = System::isAndroid();
        $isIos = System::isIos();

        return match ($platform) {
            'android' => $isAndroid,
            'ios' => $isIos,
            'mobile' => $isAndroid || $isIos,
            'desktop' => ! $isAndroid && ! $isIos,
            default => throw new InvalidArgumentException('Unrecognized platform.'),
        };
    }
} else {
    throw new Exception('The function `is_platform` already exists.');
}
