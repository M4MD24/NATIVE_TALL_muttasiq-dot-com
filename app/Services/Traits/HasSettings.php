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
    public function settingsAction(): Action
    {
        $settingDefinitions = Setting::definitions();
        $athkarSchema = [];

        foreach ($settingDefinitions as $key => $definition) {
            $checkbox = Components\Checkbox::make($key)
                ->default($definition['default'])
                ->label($definition['label']);

            if (array_key_exists('help', $definition)) {
                $checkbox->belowContent([
                    Text::make($definition['help'])->size(TextSize::ExtraSmall),
                ]);
            }

            $athkarSchema[] = $checkbox;
        }

        return Action::make('settings')
            ->label('الإعدادات')
            ->modalDescription('وبعض التفضيلات في كيفية عمل التطبيق')
            ->modalSubmitActionLabel('حفظ')
            ->fillForm(fn (): array => $this->loadSettings())
            ->schema([
                Fieldset::make('المجموعة الأولى')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->schema($athkarSchema),
            ])
            ->action(function (array $data): void {
                $savedSettings = [];

                foreach (Setting::defaults() as $name => $default) {
                    $value = array_key_exists($name, $data) ? (bool) $data[$name] : $default;
                    $savedSettings[$name] = $value;

                    Setting::query()->updateOrCreate(
                        ['name' => $name],
                        ['value' => $value],
                    );
                }

                $this->dispatch('settings-updated', settings: $savedSettings);

                notify(iconName: 'mdi.content-save-check', title: 'تم حفظ الإعدادات بنجاح');
            });
    }

    /**
     * @return array<string, bool>
     */
    private function loadSettings(): array
    {
        $storedSettings = Setting::query()->pluck('value', 'name')->all();

        return array_replace(Setting::defaults(), $storedSettings);
    }
}
