<?php

declare(strict_types=1);

namespace App\Services\Traits;

use App\Models\Setting;
use Filament\Forms\Components;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\HtmlString;

trait HasControlPanelSettingsTab
{
    private const MAIN_TEXT_SIZE_RANGE = 'main_text_size_range';

    /**
     * @return array<string, bool|int>
     */
    public static function controlPanelDefaults(): array
    {
        return Setting::defaults();
    }

    protected function controlPanelSettingsTab(): Tab
    {
        $athkarDefinitions = Setting::definitionsForGroup(Setting::GROUP_ATHKAR);
        $generalDefinitions = Setting::definitionsForGroup(Setting::GROUP_GENERAL);

        return Tab::make('الإعدادات')
            ->icon('heroicon-s-adjustments-horizontal')
            ->schema([
                Text::make('العامة')
                    ->color('black')
                    ->weight(FontWeight::Medium),

                Grid::make()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Components\Slider::make(self::MAIN_TEXT_SIZE_RANGE)
                            ->label('1. نطاق حجم النصوص المحورية (الأدنى/الأقصى).')
                            ->extraFieldWrapperAttributes(['class' => 'pb-6 sm:pb-8 md:pb-0'])
                            ->range(
                                minValue: Setting::MIN_MAIN_TEXT_SIZE_MIN,
                                maxValue: Setting::MAX_MAIN_TEXT_SIZE_MAX,
                            )
                            ->default([
                                (int) ($generalDefinitions[Setting::MINIMUM_MAIN_TEXT_SIZE]['default'] ?? Setting::MIN_MAIN_TEXT_SIZE_DEFAULT),
                                (int) ($generalDefinitions[Setting::MAXIMUM_MAIN_TEXT_SIZE]['default'] ?? Setting::MAX_MAIN_TEXT_SIZE_DEFAULT),
                            ])
                            ->step(1)
                            ->fillTrack([false, true, false])
                            ->pips(PipsMode::Steps, density: 1),

                        Components\Checkbox::make(Setting::DOES_ENABLE_MAIN_TEXT_SHIMMERING)
                            ->default((bool) ($generalDefinitions[Setting::DOES_ENABLE_MAIN_TEXT_SHIMMERING]['default'] ?? true))
                            ->extraFieldWrapperAttributes(['class' => 'relative z-20 mt-1 sm:mt-3 md:mt-0'])
                            ->label($generalDefinitions[Setting::DOES_ENABLE_MAIN_TEXT_SHIMMERING]['label']),

                        Components\Checkbox::make('does_skip_notice_panels')
                            ->default((bool) ($generalDefinitions['does_skip_notice_panels']['default'] ?? false))
                            ->extraFieldWrapperAttributes(['class' => 'relative z-20 mt-3 sm:mt-0'])
                            ->label($generalDefinitions['does_skip_notice_panels']['label']),
                    ]),

                Text::make(new HtmlString('<hr class="border-0 h-px bg-linear-to-r from-transparent via-gray-400 to-transparent mt-5">'))
                    ->extraAttributes(['class' => 'w-full']),

                Text::make('الأذكار')
                    ->color('black')
                    ->weight(FontWeight::Medium),

                Grid::make()
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
            ]);
    }
}
