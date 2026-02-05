<?php

declare(strict_types=1);

use Filament\Notifications\Notification;

if (! function_exists('notify')) {
    function notify(string $iconName, string $title, ?string $body = null): void
    {
        Notification::make()
            // ->color(is_dark_mode_on() ? 'white' : 'warning')
            ->warning()
            ->icon($iconName)
            ->iconColor(is_dark_mode_on() ? 'white' : 'warning')
            ->title($title)
            ->body($body)
            ->send();
    }
} else {
    throw new Exception('The function `notify` already exists.');
}
