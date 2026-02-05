<?php

declare(strict_types=1);

/**
 * Example:
 *
 * $someArray = php_to_js($this->someArray);
 * $this->js(<<<JS
 *     (function () {
 *         window.something = $someArray;
 *     })();
 * JS);
 */
if (! function_exists('php_to_js')) {
    function php_to_js(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
} else {
    throw new Exception('The function `php_to_js` already exists.');
}
