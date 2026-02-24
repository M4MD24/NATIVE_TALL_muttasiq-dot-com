<?php

declare(strict_types=1);

use App\Services\Support\Enums\NotificationType;
use Filament\Notifications\Notification;

if (! function_exists('notify')) {
    function notify(
        string $iconName,
        string $title,
        ?string $body = null,
        NotificationType $type = NotificationType::Default,
    ): void {
        if ($type === NotificationType::Danger) {
            Notification::make()
                ->danger()
                ->icon($iconName)
                ->iconColor(is_dark_mode_on() ? 'white' : 'primary')
                ->title($title)
                ->body($body)
                ->send();

            return;
        }

        Notification::make()
            ->warning()
            ->icon($iconName)
            ->iconColor(is_dark_mode_on() ? 'white' : 'primary')
            ->title($title)
            ->body($body)
            ->send();
    }
} else {
    throw new Exception('The function `notify` already exists.');
}
