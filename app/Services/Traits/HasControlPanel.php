<?php

declare(strict_types=1);

namespace App\Services\Traits;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;

trait HasControlPanel
{
    private const MAIN_TEXT_SIZE_RANGE = 'main_text_size_range';

    private const CONTROL_PANEL_TAB_INDEX = 1;

    private const UPDATES_TAB_INDEX = 2;

    /**
     * @var array<string, bool|int>
     */
    public array $clientControlPanel = [];

    public int $controlPanelActiveTab = self::CONTROL_PANEL_TAB_INDEX;

    /**
     * @return array<string, bool|int>
     */
    public static function controlPanelDefaults(): array
    {
        return Setting::defaults();
    }

    public function controlPanelAction(): Action
    {
        $athkarDefinitions = Setting::definitionsForGroup(Setting::GROUP_ATHKAR);
        $generalDefinitions = Setting::definitionsForGroup(Setting::GROUP_GENERAL);

        return Action::make('controlPanel')
            ->label('لوحة التحكم')
            ->modalDescription('بعض المعلومات والتفضيلات في كيفية عمل التطبيق')
            ->modalSubmitActionLabel('حفظ')
            ->fillForm(fn (): array => $this->loadControlPanel())
            ->schema([
                Tabs::make('Tabs')
                    ->activeTab(fn (): int => $this->controlPanelActiveTab)
                    ->tabs([
                        Tab::make('الإعدادات')
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
                            ]),

                        Tab::make('تحديثات')
                            ->icon('material-design.update')
                            ->schema([
                                Text::make(fn (): HtmlString => $this->changelogsMarkdown())
                                    ->extraAttributes(['class' => 'block w-full']),
                            ]),

                        Tab::make('حولنا')
                            ->icon('phosphor.warning-diamond-fill')
                            ->schema([
                                Text::make('تطبيق متسق')
                                    ->size(TextSize::Large)
                                    ->color('black')
                                    ->extraAttributes(['class' => 'block w-full text-center -mb-5']),

                                Text::make(config('app.custom.app_description'))
                                    ->size(TextSize::Small)
                                    ->color('gray')
                                    ->extraAttributes(['class' => 'block w-full text-center mt-2']),

                                Image::make(
                                    url: fn () => asset(is_dark_mode_on() ? 'icon-dark.png' : 'icon.png'),
                                    alt: 'Muttasiq application icono',
                                )
                                    ->imageSize('10rem')
                                    ->alignCenter()
                                    ->extraAttributes([
                                        'class' => 'mx-auto cursor-pointer border-0 -mt-5',
                                        'role' => 'button',
                                        'tabindex' => '0',
                                        'x-on:click' => open_link_native_aware('https://github.com/GoodM4ven/NATIVE_TALL_muttasiq-dot-com'),
                                        'x-on:keydown.enter.prevent' => open_link_native_aware('https://github.com/GoodM4ven/NATIVE_TALL_muttasiq-dot-com'),
                                        'x-on:keydown.space.prevent' => open_link_native_aware('https://github.com/GoodM4ven/NATIVE_TALL_muttasiq-dot-com'),
                                    ]),

                                Text::make('روابط سريعة:')
                                    ->size(TextSize::Medium)
                                    ->color('black')
                                    ->extraAttributes(['class' => 'block w-full text-center -mt-3']),

                                $this->developmentLinkAction(
                                    name: 'open_source_code',
                                    label: 'الترميز مفتوح المصدر',
                                    url: 'https://github.com/GoodM4ven/NATIVE_TALL_muttasiq-dot-com',
                                    icon: 'heroicon-s-code-bracket',
                                ),

                                $this->developmentLinkAction(
                                    name: 'open_main_missions',
                                    label: 'المهام الرئيسية للتطبيق',
                                    url: 'https://github.com/GoodM4ven/NATIVE_TALL_muttasiq-dot-com/?tab=readme-ov-file#%D8%A7%D9%84%D9%85%D9%87%D8%A7%D9%85',
                                    icon: 'heroicon-s-clipboard-document-list',
                                ),

                                $this->developmentLinkAction(
                                    name: 'open_discussions',
                                    label: 'الاقتراحات ونقاشها',
                                    url: 'https://github.com/GoodM4ven/NATIVE_TALL_muttasiq-dot-com/discussions',
                                    icon: 'heroicon-s-chat-bubble-left-right',
                                ),

                                $this->developmentLinkAction(
                                    name: 'open_bug_reports',
                                    label: 'التبليغ عن الأخطاء',
                                    url: 'https://github.com/GoodM4ven/NATIVE_TALL_muttasiq-dot-com/issues',
                                    icon: 'bootstrap.x-circle-fill',
                                ),

                                $this->developmentLinkAction(
                                    name: 'open_current_development',
                                    label: 'التطوير التفصيلي الحالي',
                                    url: 'https://github.com/users/GoodM4ven/projects/5/views/1',
                                    icon: 'ant-design.code',
                                ),

                                $this->developmentLinkAction(
                                    name: 'open_project_support',
                                    label: 'دعم المشروع',
                                    url: 'https://github.com/GoodM4ven/NATIVE_TALL_muttasiq-dot-com?tab=readme-ov-file#%D8%A7%D9%84%D8%AF%D8%B9%D9%85',
                                    icon: 'bootstrap.lightning-charge-fill',
                                ),
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

                $savedControlPanel = Setting::normalizeSettings($data);

                $this->clientControlPanel = $savedControlPanel;
                $this->dispatch('control-panel-updated', controlPanel: $savedControlPanel);

                notify(iconName: 'mdi.content-save-check', title: 'تم حفظ الإعدادات بنجاح');
            });
    }

    public function setControlPanelActiveTab(?string $tab = null): void
    {
        $this->controlPanelActiveTab = $tab === 'updates'
            ? self::UPDATES_TAB_INDEX
            : self::CONTROL_PANEL_TAB_INDEX;
    }

    /**
     * @param  array<string, mixed>  $controlPanel
     */
    public function openControlPanelModal(array $controlPanel = [], ?string $tab = null): void
    {
        $this->syncClientControlPanel($controlPanel);
        $this->setControlPanelActiveTab($tab);
        $this->mountAction('controlPanel');
    }

    /**
     * @param  array<string, mixed>  $controlPanel
     */
    public function syncClientControlPanel(array $controlPanel): void
    {
        $this->clientControlPanel = $this->filterControlPanel($controlPanel);
    }

    /**
     * @return array<string, bool|int|list<int>>
     */
    private function loadControlPanel(): array
    {
        $storedControlPanelValues = Setting::query()
            ->whereIn('name', array_keys(self::controlPanelDefaults()))
            ->pluck('value', 'name')
            ->all();

        $normalizedControlPanelValues = Setting::normalizeSettings(
            array_replace(self::controlPanelDefaults(), $storedControlPanelValues, $this->clientControlPanel),
        );

        $normalizedControlPanelValues[self::MAIN_TEXT_SIZE_RANGE] = [
            (int) ($normalizedControlPanelValues[Setting::MINIMUM_MAIN_TEXT_SIZE] ?? Setting::MIN_MAIN_TEXT_SIZE_DEFAULT),
            (int) ($normalizedControlPanelValues[Setting::MAXIMUM_MAIN_TEXT_SIZE] ?? Setting::MAX_MAIN_TEXT_SIZE_DEFAULT),
        ];

        return $normalizedControlPanelValues;
    }

    /**
     * @param  array<string, mixed>  $controlPanel
     * @return array<string, bool|int>
     */
    private function filterControlPanel(array $controlPanel): array
    {
        return Setting::normalizeSettings(
            array_intersect_key($controlPanel, self::controlPanelDefaults()),
        );
    }

    private function developmentLinkAction(string $name, string $label, string $url, string $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->link()
            ->extraAttributes(['class' => 'flex w-fit mx-auto text-[0.8rem]! items-center gap-1.5 whitespace-nowrap text-center -mt-3'])
            ->actionJs(open_link_native_aware(url: $url));
    }

    private function changelogsMarkdown(): HtmlString
    {
        $markdown = File::get(public_path('docs/updates/changelogs.md'));

        $markdown = preg_replace('/^\s*<div align="right">\s*/', '', $markdown) ?? $markdown;
        $markdown = preg_replace('/\s*<\/div>\s*$/', '', $markdown) ?? $markdown;
        $markdown = $this->rewriteChangelogMarkdownImageSources($markdown);

        $html = str($markdown)
            ->markdown()
            ->sanitizeHtml()
            ->toString();

        $html = preg_replace_callback(
            '/<a\b[^>]*href="([^"]+)"[^>]*>/i',
            function (array $matches): string {
                $tag = $matches[0];

                if (str_contains($tag, 'x-on:click.prevent=')) {
                    return $tag;
                }

                $href = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $openLinkNativeAware = htmlspecialchars(open_link_native_aware($href), ENT_QUOTES, 'UTF-8');

                return rtrim(substr($tag, 0, -1))
                    .' x-on:click.prevent="'.$openLinkNativeAware.'"'
                    .' x-on:keydown.enter.prevent="'.$openLinkNativeAware.'"'
                    .' x-on:keydown.space.prevent="'.$openLinkNativeAware.'">';
            },
            $html,
        ) ?? $html;

        return new HtmlString(<<<HTML
            <article class="mx-auto w-full max-w-3xl text-right leading-7
                [&_h2]:mt-12 [&_h2:first-child]:mt-0 [&_h2]:mb-4 [&_h2]:border-b [&_h2]:border-gray-200 [&_h2]:pb-2 [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-gray-800 dark:[&_h2]:border-gray-700 dark:[&_h2]:text-gray-100
                [&_h3]:mt-3 [&_h3]:mb-1.5 [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-gray-700 dark:[&_h3]:text-gray-200
                [&_p]:my-1 [&_p]:text-sm [&_p]:text-gray-600 dark:[&_p]:text-gray-300
                [&_ul]:my-2 [&_ul]:list-disc [&_ul]:list-inside [&_ul]:space-y-1
                [&_li]:text-sm [&_li]:text-gray-700 dark:[&_li]:text-gray-200
                [&_a]:font-medium [&_a]:text-info-600 hover:[&_a]:text-info-700 dark:[&_a]:text-primary-400 dark:hover:[&_a]:text-primary-500
                [&_img]:my-4 [&_img]:mx-auto [&_img]:h-auto [&_img]:rounded-lg [&_img]:border [&_img]:border-gray-200 dark:[&_img]:border-gray-700">
                {$html}
            </article>
            HTML);
    }

    private function rewriteChangelogMarkdownImageSources(string $markdown): string
    {
        $baseUrl = $this->changelogImageBaseUrl();

        $markdown = preg_replace_callback(
            '/!\[([^\]]*)\]\(images\/([^)\\s]+)\)/',
            function (array $matches) use ($baseUrl): string {
                $imageSource = $baseUrl.$matches[2];

                return '!['.$matches[1].']('.$imageSource.')';
            },
            $markdown,
        ) ?? $markdown;

        $markdown = preg_replace_callback(
            '/\bsrc=(["\'])images\/([^"\']+)\1/i',
            function (array $matches) use ($baseUrl): string {
                $imageSource = $baseUrl.$matches[2];

                return 'src='.$matches[1].$imageSource.$matches[1];
            },
            $markdown,
        ) ?? $markdown;

        return str_replace('](images/', ']('.$baseUrl, $markdown);
    }

    private function changelogImageBaseUrl(): string
    {
        if (config('nativephp-internal.running')) {
            return '/_assets/docs/updates/images/';
        }

        return '/docs/updates/images/';
    }
}
