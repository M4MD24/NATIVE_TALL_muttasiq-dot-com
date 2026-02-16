<?php

declare(strict_types=1);

namespace App\Services\Traits;

use App\Models\Setting;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\TextSize;

trait HasSettings
{
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
                        Components\TextInput::make(Setting::MINIMUM_MAIN_TEXT_SIZE)
                            ->default((int) ($generalDefinitions[Setting::MINIMUM_MAIN_TEXT_SIZE]['default'] ?? Setting::MIN_MAIN_TEXT_SIZE_DEFAULT))
                            ->label($generalDefinitions[Setting::MINIMUM_MAIN_TEXT_SIZE]['label'])
                            ->numeric()
                            ->step(1)
                            ->inputMode('numeric')
                            ->minValue(Setting::MIN_MAIN_TEXT_SIZE_MIN)
                            ->maxValue(Setting::MIN_MAIN_TEXT_SIZE_MAX)
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $minimumValue = is_numeric($value) ? (int) $value : Setting::MIN_MAIN_TEXT_SIZE_DEFAULT;
                                    $maximumValue = (int) ($get(Setting::MAXIMUM_MAIN_TEXT_SIZE) ?? Setting::MAX_MAIN_TEXT_SIZE_DEFAULT);

                                    if ($minimumValue > $maximumValue) {
                                        $fail('الحد الأدنى لا يمكن أن يكون أكبر من الحد الأقصى.');
                                    }
                                },
                            ])
                            ->required(),

                        Components\TextInput::make(Setting::MAXIMUM_MAIN_TEXT_SIZE)
                            ->default((int) ($generalDefinitions[Setting::MAXIMUM_MAIN_TEXT_SIZE]['default'] ?? Setting::MAX_MAIN_TEXT_SIZE_DEFAULT))
                            ->label($generalDefinitions[Setting::MAXIMUM_MAIN_TEXT_SIZE]['label'])
                            ->numeric()
                            ->step(1)
                            ->inputMode('numeric')
                            ->minValue(Setting::MAX_MAIN_TEXT_SIZE_MIN)
                            ->maxValue(Setting::MAX_MAIN_TEXT_SIZE_MAX)
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $maximumValue = is_numeric($value) ? (int) $value : Setting::MAX_MAIN_TEXT_SIZE_DEFAULT;
                                    $minimumValue = (int) ($get(Setting::MINIMUM_MAIN_TEXT_SIZE) ?? Setting::MIN_MAIN_TEXT_SIZE_DEFAULT);

                                    if ($maximumValue < $minimumValue) {
                                        $fail('الحد الأقصى لا يمكن أن يكون أصغر من الحد الأدنى.');
                                    }
                                },
                            ])
                            ->required(),

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

        return Setting::normalizeSettings(
            array_replace(self::settingsDefaults(), $storedSettings, $this->clientSettings),
        );
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
