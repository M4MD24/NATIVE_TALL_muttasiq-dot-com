<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebHomeActivityTracker
{
    public function track(Request $request): void
    {
        $now = CarbonImmutable::now();

        $this->incrementWithTtl(
            key: $this->dailyHitsKey($now),
            ttlSeconds: $this->dailyCounterTtlSeconds($now),
        );
        $this->incrementWithTtl(
            key: $this->hourlyHitsKey($now),
            ttlSeconds: $this->hourlyCounterTtlSeconds($now),
        );

        $fingerprint = $this->fingerprint($request);

        $isNewDailyVisitor = Cache::add(
            $this->dailySeenKey($now, $fingerprint),
            true,
            now()->addDays($this->retentionDays()),
        );

        if ($isNewDailyVisitor) {
            $this->incrementWithTtl(
                key: $this->dailyUniqueKey($now),
                ttlSeconds: $this->dailyCounterTtlSeconds($now),
            );
        }

        $isNewHourlyVisitor = Cache::add(
            $this->hourlySeenKey($now, $fingerprint),
            true,
            now()->addDays(2),
        );

        if ($isNewHourlyVisitor) {
            $this->incrementWithTtl(
                key: $this->hourlyUniqueKey($now),
                ttlSeconds: $this->hourlyCounterTtlSeconds($now),
            );
        }
    }

    /**
     * @return array{labels: array<int, string>, hits: array<int, int>, unique_visitors: array<int, int>}
     */
    public function dailySeries(int $days): array
    {
        $days = max(1, $days);
        $endDate = CarbonImmutable::today();

        $labels = [];
        $hits = [];
        $uniqueVisitors = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = $endDate->subDays($offset);
            $labels[] = $date->format('M d');
            $hits[] = (int) Cache::get($this->dailyHitsKey($date), 0);
            $uniqueVisitors[] = (int) Cache::get($this->dailyUniqueKey($date), 0);
        }

        return [
            'labels' => $labels,
            'hits' => $hits,
            'unique_visitors' => $uniqueVisitors,
        ];
    }

    /**
     * @return array{hits: int, unique_visitors: int}
     */
    public function todaySummary(): array
    {
        $today = CarbonImmutable::today();

        return [
            'hits' => (int) Cache::get($this->dailyHitsKey($today), 0),
            'unique_visitors' => (int) Cache::get($this->dailyUniqueKey($today), 0),
        ];
    }

    /**
     * @return array{hits: int, unique_visitors: int}
     */
    public function last24HoursSummary(): array
    {
        $currentHour = CarbonImmutable::now()->startOfHour();
        $hits = 0;
        $uniqueVisitors = 0;

        for ($offset = 0; $offset < 24; $offset++) {
            $hour = $currentHour->subHours($offset);
            $hits += (int) Cache::get($this->hourlyHitsKey($hour), 0);
            $uniqueVisitors += (int) Cache::get($this->hourlyUniqueKey($hour), 0);
        }

        return [
            'hits' => $hits,
            'unique_visitors' => $uniqueVisitors,
        ];
    }

    public function chartDays(): int
    {
        return max(7, (int) config('app.custom.security.web_home_metrics.chart_days', 14));
    }

    private function retentionDays(): int
    {
        return max(2, (int) config('app.custom.security.web_home_metrics.retention_days', 35));
    }

    private function dailyCounterTtlSeconds(CarbonImmutable $timestamp): int
    {
        return max(60, $timestamp->endOfDay()->addDays($this->retentionDays())->diffInSeconds($timestamp));
    }

    private function hourlyCounterTtlSeconds(CarbonImmutable $timestamp): int
    {
        return max(60, $timestamp->endOfHour()->addDays(2)->diffInSeconds($timestamp));
    }

    private function incrementWithTtl(string $key, int $ttlSeconds): int
    {
        Cache::add($key, 0, $ttlSeconds);

        return (int) Cache::increment($key);
    }

    private function fingerprint(Request $request): string
    {
        $ipAddress = trim((string) $request->ip());
        $userAgent = Str::limit((string) $request->userAgent(), 255, '');

        return hash('sha256', $ipAddress.'|'.$userAgent);
    }

    private function dailyHitsKey(CarbonImmutable $date): string
    {
        return 'metrics:web-home:daily:hits:'.$date->format('Ymd');
    }

    private function dailyUniqueKey(CarbonImmutable $date): string
    {
        return 'metrics:web-home:daily:unique:'.$date->format('Ymd');
    }

    private function dailySeenKey(CarbonImmutable $date, string $fingerprint): string
    {
        return 'metrics:web-home:daily:seen:'.$date->format('Ymd').':'.$fingerprint;
    }

    private function hourlyHitsKey(CarbonImmutable $date): string
    {
        return 'metrics:web-home:hourly:hits:'.$date->format('YmdH');
    }

    private function hourlyUniqueKey(CarbonImmutable $date): string
    {
        return 'metrics:web-home:hourly:unique:'.$date->format('YmdH');
    }

    private function hourlySeenKey(CarbonImmutable $date, string $fingerprint): string
    {
        return 'metrics:web-home:hourly:seen:'.$date->format('YmdH').':'.$fingerprint;
    }
}
