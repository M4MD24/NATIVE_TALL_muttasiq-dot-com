<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Monitoring\WebHomeActivityTracker;
use Filament\Widgets\ChartWidget;

class WebHomeActivityChart extends ChartWidget
{
    protected ?string $heading = 'نشاط الزيارات';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected ?string $pollingInterval = '30s';

    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $tracker = app(WebHomeActivityTracker::class);
        $activity = $tracker->dailySeries(days: $tracker->chartDays());

        return [
            'datasets' => [
                [
                    'label' => 'زيارات',
                    'data' => $activity['hits'],
                    'borderColor' => '#0a4457',
                    'backgroundColor' => 'rgba(10, 68, 87, 0.18)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'زائرون مميّزون',
                    'data' => $activity['unique_visitors'],
                    'borderColor' => '#fbb937',
                    'backgroundColor' => 'rgba(251, 185, 55, 0.16)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $activity['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    public function getDescription(): ?string
    {
        $tracker = app(WebHomeActivityTracker::class);
        $today = $tracker->todaySummary();
        $last24Hours = $tracker->last24HoursSummary();

        return sprintf(
            'اليوم: %d مميّزة / %d زيارات. آخر 24 ساعة: %d مميّزة / %d زيارات.',
            $today['unique_visitors'],
            $today['hits'],
            $last24Hours['unique_visitors'],
            $last24Hours['hits'],
        );
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
