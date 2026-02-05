<?php

declare(strict_types=1);

use App\Services\Enums\ViewName;

if (! function_exists('view_title')) {
    function view_title(ViewName $viewName): string
    {
        $appName = __('custom/general.app_name');
        $title = match ($viewName) {
            ViewName::MainMenu => 'الرئيسية',
        };

        return "$title | $appName";
    }
} else {
    throw new Exception('The function `view_title` already exists.');
}
