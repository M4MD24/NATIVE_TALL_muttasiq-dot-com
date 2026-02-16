<?php

declare(strict_types=1);

namespace App\Services\Traits;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\TextSize;

trait HasSettings
{
    private const MAIN_TEXT_SIZE_RANGE = 'main_text_size_range';

    /**
     * @var array<string, bool|int>
     */
    public array $clientSettings = [];

    /**
     * @return array<string, bool|int>
     */
    public static function settingsDefaults(): array
    {
        return Setting::defaults();
    }

    public function settingsAction(): Action
    {
        $athkarDefinitions = Setting::definitionsForGroup(Setting::GROUP_ATHKAR);
        $generalDefinitions = Setting::definitionsForGroup(Setting::GROUP_GENERAL);

        return Action::make('settings')
            ->label('الإعدادات')
            ->modalDescription('وبعض التفضيلات في كيفية عمل التطبيق')
            ->modalSubmitActionLabel('حفظ')
            ->fillForm(fn (): array => $this->loadSettings())
            ->schema([
                Fieldset::make('العامة')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Components\Slider::make(self::MAIN_TEXT_SIZE_RANGE)
                            ->label('1. نطاق حجم النصوص المحورية (الأدنى/الأقصى).')
                            ->range(
                                minValue: Setting::MIN_MAIN_TEXT_SIZE_MIN,
                                maxValue: Setting::MAX_MAIN_TEXT_SIZE_MAX,
                            )
                            ->default([
                                (int) ($generalDefinitions[Setting::MINIMUM_MAIN_TEXT_SIZE]['default'] ?? Setting::MIN_MAIN_TEXT_SIZE_DEFAULT),
                                (int) ($generalDefinitions[Setting::MAXIMUM_MAIN_TEXT_SIZE]['default'] ?? Setting::MAX_MAIN_TEXT_SIZE_DEFAULT),
                            ])
                            ->step(1)
                            ->tooltips()
                            ->fillTrack([false, true, false]),

                        Components\Checkbox::make('does_skip_notice_panels')
                            ->default((bool) ($generalDefinitions['does_skip_notice_panels']['default'] ?? false))
                            ->label($generalDefinitions['does_skip_notice_panels']['label']),
                    ]),
                Fieldset::make('الأذكار')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema([
                        Components\Checkbox::make('does_automatically_switch_completed_athkar')
                            ->default((bool) ($athkarDefinitions['does_automatically_switch_completed_athkar']['default'] ?? true))
                            ->label($athkarDefinitions['does_automatically_switch_completed_athkar']['label']),

                        Components\Checkbox::make('does_clicking_switch_athkar_too')
                            ->default((bool) ($athkarDefinitions['does_clicking_switch_athkar_too']['default'] ?? true))
                            ->label($athkarDefinitions['does_clicking_switch_athkar_too']['label'])
                            ->belowContent([
                                Text::make((string) ($athkarDefinitions['does_clicking_switch_athkar_too']['help'] ?? ''))->size(TextSize::ExtraSmall),
                            ]),

                        Components\Checkbox::make('does_prevent_switching_athkar_until_completion')
                            ->default((bool) ($athkarDefinitions['does_prevent_switching_athkar_until_completion']['default'] ?? true))
                            ->label($athkarDefinitions['does_prevent_switching_athkar_until_completion']['label'])
                            ->belowContent([
                                Text::make((string) ($athkarDefinitions['does_prevent_switching_athkar_until_completion']['help'] ?? ''))->size(TextSize::ExtraSmall),
                            ]),
                    ]),
            ])
            ->action(function (array $data): void {
                if (is_array($data[self::MAIN_TEXT_SIZE_RANGE] ?? null)) {
                    $rangeValues = array_values($data[self::MAIN_TEXT_SIZE_RANGE]);
                    $minimumSize = (int) ($rangeValues[0] ?? Setting::MIN_MAIN_TEXT_SIZE_DEFAULT);
                    $maximumSize = (int) ($rangeValues[1] ?? Setting::MAX_MAIN_TEXT_SIZE_DEFAULT);
                    $data[Setting::MINIMUM_MAIN_TEXT_SIZE] = min($minimumSize, $maximumSize);
                    $data[Setting::MAXIMUM_MAIN_TEXT_SIZE] = max($minimumSize, $maximumSize);
                }

                $savedSettings = Setting::normalizeSettings($data);

                $this->clientSettings = $savedSettings;
                $this->dispatch('settings-updated', settings: $savedSettings);

                notify(iconName: 'mdi.content-save-check', title: 'تم حفظ الإعدادات بنجاح');
            });
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function syncClientSettings(array $settings): void
    {
        $this->clientSettings = $this->filterSettings($settings);
    }

    /**
     * @return array<string, bool|int>
     */
    private function loadSettings(): array
    {
        $storedSettings = Setting::query()
            ->whereIn('name', array_keys(self::settingsDefaults()))
            ->pluck('value', 'name')
            ->all();

        $normalizedSettings = Setting::normalizeSettings(
            array_replace(self::settingsDefaults(), $storedSettings, $this->clientSettings),
        );

        $normalizedSettings[self::MAIN_TEXT_SIZE_RANGE] = [
            (int) ($normalizedSettings[Setting::MINIMUM_MAIN_TEXT_SIZE] ?? Setting::MIN_MAIN_TEXT_SIZE_DEFAULT),
            (int) ($normalizedSettings[Setting::MAXIMUM_MAIN_TEXT_SIZE] ?? Setting::MAX_MAIN_TEXT_SIZE_DEFAULT),
        ];

        return $normalizedSettings;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, bool|int>
     */
    private function filterSettings(array $settings): array
    {
        return Setting::normalizeSettings(
            array_intersect_key($settings, self::settingsDefaults()),
        );
    }
}
