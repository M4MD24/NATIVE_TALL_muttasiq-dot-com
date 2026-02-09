<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Enums\ThikrTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $thikr_id
 * @property int|null $order
 * @property array<int, string>|null $overrides
 * @property ThikrTime|null $time
 * @property string|null $text
 * @property int|null $count
 * @property bool $is_deleted
 */
class OverriddenThikr extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overrides' => 'array',
            'time' => ThikrTime::class,
            'count' => 'integer',
            'order' => 'integer',
            'is_deleted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Thikr, $this>
     */
    public function thikr(): BelongsTo
    {
        return $this->belongsTo(Thikr::class);
    }
}
