<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const GROUP_GENERAL = 'general';

    public const GROUP_ATHKAR = 'athkar';

    public const DOES_ENABLE_MAIN_TEXT_SHIMMERING = 'does_enable_main_text_shimmering';

    public const MINIMUM_MAIN_TEXT_SIZE = 'minimum_main_text_size';

    public const MAXIMUM_MAIN_TEXT_SIZE = 'maximum_main_text_size';

    public const MIN_MAIN_TEXT_SIZE_MIN = 14;

    public const MIN_MAIN_TEXT_SIZE_MAX = 24;

    public const MIN_MAIN_TEXT_SIZE_DEFAULT = 21;

    public const MAX_MAIN_TEXT_SIZE_MIN = 14;

    public const MAX_MAIN_TEXT_SIZE_MAX = 24;

    public const MAX_MAIN_TEXT_SIZE_DEFAULT = 22;

    /**
     * @return array<string, array{default: bool|int, label: string, group: string, type: 'boolean'|'integer', help?: string, min?: int, max?: int}>
     */
    public static function definitions(): array
    {
        return [
            'does_automatically_switch_completed_athkar' => [
                'default' => true,
                'label' => '1. الانتقال التلقائي عند اكتمال عدد الذكر.',
                'group' => self::GROUP_ATHKAR,
                'type' => 'boolean',
            ],
            'does_clicking_switch_athkar_too' => [
                'default' => true,
                'label' => '2. الضغط والنقر يقوم بالانتقال أيضا للذكر التالي، وليس مجرد السحب فحسب.',
                'help' => 'ولكن إن قمت بالعودة للأذكار التامة، أو كان الخيار الأذكار (1) معطلا، فالضغط يقوم بزيادة العدّ.',
                'group' => self::GROUP_ATHKAR,
                'type' => 'boolean',
            ],
            'does_prevent_switching_athkar_until_completion' => [
                'default' => true,
                'label' => '3. المنع من الانتقال بين الأذكار حتى إنهائها أولًا.',
                'help' => 'وكذلك يقوم بالسماح بإعادة استعراض أذكار الصباح والمساء حتى عند إتمامها.',
                'group' => self::GROUP_ATHKAR,
                'type' => 'boolean',
            ],
            self::MINIMUM_MAIN_TEXT_SIZE => [
                'default' => self::MIN_MAIN_TEXT_SIZE_DEFAULT,
                'label' => '1. الحد الأدنى لحجم النصوص المحورية.',
                'group' => self::GROUP_GENERAL,
                'type' => 'integer',
                'min' => self::MIN_MAIN_TEXT_SIZE_MIN,
                'max' => self::MIN_MAIN_TEXT_SIZE_MAX,
            ],
            self::MAXIMUM_MAIN_TEXT_SIZE => [
                'default' => self::MAX_MAIN_TEXT_SIZE_DEFAULT,
                'label' => '2. الحد الأقصى لحجم النصوص المحورية.',
                'group' => self::GROUP_GENERAL,
                'type' => 'integer',
                'min' => self::MAX_MAIN_TEXT_SIZE_MIN,
                'max' => self::MAX_MAIN_TEXT_SIZE_MAX,
            ],
            self::DOES_ENABLE_MAIN_TEXT_SHIMMERING => [
                'default' => true,
                'label' => '2. تحسين التأثيرات البصرية وتجميل النصوص المحويرة.',
                'group' => self::GROUP_GENERAL,
                'type' => 'boolean',
            ],
            'does_skip_notice_panels' => [
                'default' => false,
                'label' => '3. تجاوز رسائل التعريف أو التهنئة وما شابه.',
                'group' => self::GROUP_GENERAL,
                'type' => 'boolean',
            ],
        ];
    }

    /**
     * @return array<string, array{default: bool|int, label: string, group: string, type: 'boolean'|'integer', help?: string, min?: int, max?: int}>
     */
    public static function definitionsForGroup(string $group): array
    {
        return array_filter(
            self::definitions(),
            static fn (array $definition): bool => $definition['group'] === $group,
        );
    }

    /**
     * @return array<string, bool|int>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::definitions() as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }

        return $defaults;
    }

    /**
     * @return array{
     *     minimum_main_text_size: array{min: int, max: int, default: int},
     *     maximum_main_text_size: array{min: int, max: int, default: int}
     * }
     */
    public static function mainTextSizeLimits(): array
    {
        $definitions = self::definitions();
        $minimumDefinition = $definitions[self::MINIMUM_MAIN_TEXT_SIZE] ?? [];
        $maximumDefinition = $definitions[self::MAXIMUM_MAIN_TEXT_SIZE] ?? [];

        return [
            self::MINIMUM_MAIN_TEXT_SIZE => [
                'min' => (int) ($minimumDefinition['min'] ?? self::MIN_MAIN_TEXT_SIZE_MIN),
                'max' => (int) ($minimumDefinition['max'] ?? self::MIN_MAIN_TEXT_SIZE_MAX),
                'default' => (int) ($minimumDefinition['default'] ?? self::MIN_MAIN_TEXT_SIZE_DEFAULT),
            ],
            self::MAXIMUM_MAIN_TEXT_SIZE => [
                'min' => (int) ($maximumDefinition['min'] ?? self::MAX_MAIN_TEXT_SIZE_MIN),
                'max' => (int) ($maximumDefinition['max'] ?? self::MAX_MAIN_TEXT_SIZE_MAX),
                'default' => (int) ($maximumDefinition['default'] ?? self::MAX_MAIN_TEXT_SIZE_DEFAULT),
            ],
        ];
    }

    public static function normalizeValue(string $name, mixed $value): bool|int
    {
        $definition = self::definitions()[$name] ?? null;

        if (! is_array($definition)) {
            if (is_bool($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }

            return false;
        }

        if ($definition['type'] === 'integer') {
            $numericValue = is_numeric($value) ? (int) $value : (int) $definition['default'];
            $minimum = (int) ($definition['min'] ?? $definition['default']);
            $maximum = (int) ($definition['max'] ?? $definition['default']);

            return max($minimum, min($maximum, $numericValue));
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1') {
            return true;
        }

        if ($value === 0 || $value === '0') {
            return false;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalized !== null) {
            return $normalized;
        }

        return (bool) $definition['default'];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, bool|int>
     */
    public static function normalizeSettings(array $settings): array
    {
        $normalized = [];

        foreach (self::definitions() as $name => $definition) {
            $value = array_key_exists($name, $settings) ? $settings[$name] : $definition['default'];
            $normalized[$name] = self::normalizeValue($name, $value);
        }

        $minimumMainTextSize = (int) ($normalized[self::MINIMUM_MAIN_TEXT_SIZE] ?? self::MIN_MAIN_TEXT_SIZE_DEFAULT);
        $maximumMainTextSize = (int) ($normalized[self::MAXIMUM_MAIN_TEXT_SIZE] ?? self::MAX_MAIN_TEXT_SIZE_DEFAULT);

        if ($minimumMainTextSize > $maximumMainTextSize) {
            $minimumMainTextSize = $maximumMainTextSize;
        }

        $normalized[self::MINIMUM_MAIN_TEXT_SIZE] = $minimumMainTextSize;
        $normalized[self::MAXIMUM_MAIN_TEXT_SIZE] = max($maximumMainTextSize, $minimumMainTextSize);

        return $normalized;
    }
}
