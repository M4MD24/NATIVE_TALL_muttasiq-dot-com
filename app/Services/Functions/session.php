<?php

declare(strict_types=1);

if (! function_exists('resolve_session_boolean')) {
    function resolve_session_boolean(mixed $value): ?bool
    {
        if (is_array($value)) {
            $lastKey = array_key_last($value);

            if (is_null($lastKey)) {
                return null;
            }

            $value = $value[$lastKey];
        }

        if (is_null($value)) {
            return null;
        }

        return (bool) $value;
    }
} else {
    throw new Exception('The function `resolve_session_boolean` already exists.');
}
