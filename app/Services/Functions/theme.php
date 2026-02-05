<?php

declare(strict_types=1);

use Filament\Support\Colors\Color;

if (! function_exists('is_dark_mode_on')) {
    function is_dark_mode_on(): bool
    {
        return resolve_session_boolean(session('is-dark-mode-on')) ?? false;
    }
} else {
    throw new Exception('The function `is_dark_mode_on` already exists.');
}

if (! function_exists('theme_color')) {
    function theme_color(string $name, ?string $default = null): string
    {
        $colors = theme_css_variables();
        $key = ltrim($name, '-');

        if (array_key_exists($key, $colors)) {
            return theme_color_to_hex(resolve_theme_css_variable($key, $colors));
        }

        if (! is_null($default)) {
            return theme_color_to_hex(resolve_theme_css_variable_value($default, $colors));
        }

        throw new InvalidArgumentException("Theme color [{$name}] not found.");
    }
} else {
    throw new Exception('The function `theme_color` already exists.');
}

if (! function_exists('resolve_theme_css_variable')) {
    /**
     * @param  array<string, string>  $variables
     * @param  array<string, bool>  $seen
     */
    function resolve_theme_css_variable(string $name, array $variables, array $seen = []): string
    {
        if (isset($seen[$name])) {
            return $variables[$name] ?? '';
        }

        $seen[$name] = true;

        return resolve_theme_css_variable_value($variables[$name] ?? '', $variables, $seen);
    }
} else {
    throw new Exception('The function `resolve_theme_css_variable` already exists.');
}

if (! function_exists('resolve_theme_css_variable_value')) {
    /**
     * @param  array<string, string>  $variables
     * @param  array<string, bool>  $seen
     */
    function resolve_theme_css_variable_value(string $value, array $variables, array $seen = []): string
    {
        $value = trim($value);

        if (preg_match('/^var\\(--([a-zA-Z0-9\\-]+)(?:,\\s*([^\\)]+))?\\)$/', $value, $matches) === 1) {
            $variableName = $matches[1];
            $fallback = $matches[2] ?? null;

            if (array_key_exists($variableName, $variables)) {
                return resolve_theme_css_variable($variableName, $variables, $seen);
            }

            if (is_string($fallback)) {
                return resolve_theme_css_variable_value($fallback, $variables, $seen);
            }
        }

        return $value;
    }
} else {
    throw new Exception('The function `resolve_theme_css_variable_value` already exists.');
}

if (! function_exists('theme_color_to_hex')) {
    function theme_color_to_hex(string $color): string
    {
        $color = trim($color);

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $matches) === 1) {
            $hex = $matches[1];

            return '#'.strtolower("{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}");
        }

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $color, $matches) === 1) {
            return '#'.strtolower($matches[1]);
        }

        $rgb = str_replace(' ', '', Color::convertToRgb($color));

        if (preg_match('/^rgb\\((\\d+),(\\d+),(\\d+)\\)$/', $rgb, $matches) === 1) {
            return sprintf('#%02x%02x%02x', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        throw new InvalidArgumentException("Theme color [{$color}] could not be converted to hex.");
    }
} else {
    throw new Exception('The function `theme_color_to_hex` already exists.');
}

if (! function_exists('theme_css_variables')) {
    /**
     * @return array<string, string>
     */
    function theme_css_variables(): array
    {
        static $variables = null;

        if (is_array($variables)) {
            return $variables;
        }

        $variables = [];

        $path = resource_path('css/app.css');

        if (file_exists($path)) {
            $contents = file_get_contents($path);

            if ($contents !== false) {
                $contents = preg_replace('/\/\*.*?\*\//s', '', $contents) ?? $contents;

                preg_match_all('/--([a-zA-Z0-9\-]+)\s*:\s*([^;]+);/', $contents, $matches, PREG_SET_ORDER);

                foreach ($matches as $match) {
                    $variables[$match[1]] = trim($match[2]);
                }
            }
        }

        $customColors = config('app.custom.colors', []);

        if (is_array($customColors)) {
            foreach ($customColors as $name => $color) {
                if (! is_string($name)) {
                    continue;
                }

                $palette = null;

                if (is_string($color)) {
                    $palette = Color::generatePalette($color);
                } elseif (is_array($color)) {
                    $palette = [];

                    foreach ($color as $shade => $shadeColor) {
                        $palette[$shade] = is_string($shadeColor) ? Color::convertToOklch($shadeColor) : $shadeColor;
                    }
                }

                if (! is_array($palette)) {
                    continue;
                }

                foreach ($palette as $shade => $shadeColor) {
                    if (! is_int($shade) && ! ctype_digit((string) $shade)) {
                        continue;
                    }

                    $key = "{$name}-{$shade}";

                    if (array_key_exists($key, $variables)) {
                        continue;
                    }

                    $variables[$key] = (string) $shadeColor;
                }
            }
        }

        return $variables;
    }
} else {
    throw new Exception('The function `theme_css_variables` already exists.');
}
