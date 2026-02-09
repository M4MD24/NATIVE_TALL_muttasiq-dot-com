<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Enums\ThikrTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property ThikrTime $time
 * @property string $text
 * @property int $count
 * @property int $order
 */
class Thikr extends Model implements Sortable
{
    use HasFactory;
    use SortableTrait;

    public const DEFAULT_CACHE_KEY = 'athkar.defaults.v1';

    public const DEFAULT_CACHE_TTL_SECONDS = 604800; // ? 1 week

    protected static function booted(): void
    {
        static::saved(function (self $thikr): void {
            self::clearDefaultCache();
        });

        static::deleted(function (self $thikr): void {
            self::clearDefaultCache();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time' => ThikrTime::class,
            'count' => 'integer',
            'order' => 'integer',
        ];
    }

    /**
     * @return array<int, array{id: int, time: string, text: string, count: int, order: int}>
     */
    public static function cachedDefaults(): array
    {
        return Cache::remember(
            self::DEFAULT_CACHE_KEY,
            now()->addSeconds(self::DEFAULT_CACHE_TTL_SECONDS),
            fn (): array => self::defaultsPayload(),
        );
    }

    public static function clearDefaultCache(): void
    {
        Cache::forget(self::DEFAULT_CACHE_KEY);
    }

    /**
     * @return array<int, array{id: int, time: string, text: string, count: int, order: int}>
     */
    public static function defaultsPayload(): array
    {
        return self::query()
            ->ordered()
            ->get(['id', 'time', 'text', 'count', 'order'])
            ->map(fn (self $thikr): array => $thikr->toAthkarArray())
            ->all();
    }

    /**
     * @return array{id: int, time: string, text: string, count: int, order: int}
     */
    public function toAthkarArray(): array
    {
        return [
            'id' => $this->id,
            'time' => $this->time->value,
            'text' => $this->text,
            'count' => $this->count,
            'order' => $this->order,
        ];
    }
}
