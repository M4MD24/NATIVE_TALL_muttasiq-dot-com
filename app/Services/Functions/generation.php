<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

if (! function_exists('random_icon')) {
    function random_icon(): string
    {
        $path = base_path('vendor/blade-ui-kit/blade-heroicons/resources/svg/');

        return 'heroicon-'.pathinfo(collect(File::files($path))->random()->getFilename(), PATHINFO_FILENAME);
    }
} else {
    throw new Exception('The function `random_icon` already exists.');
}
