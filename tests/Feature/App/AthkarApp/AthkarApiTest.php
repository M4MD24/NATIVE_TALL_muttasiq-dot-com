<?php

declare(strict_types=1);

use App\Models\Thikr;
use App\Services\Enums\ThikrType;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\getJson;

it('uses the expected local athkar route and returns ordered fresh cached payloads', function () {
    expect(route('api.athkar.index', [], false))->toBe('/api/athkar');

    RateLimiter::for('athkar', fn (Request $request): Limit => Limit::none());

    Thikr::factory()->count(3)->create();

    $response = getJson(route('api.athkar.index'));

    $response->assertSuccessful();

    $expected = Thikr::query()
        ->ordered()
        ->get(['id', 'time', 'type', 'text', 'origin', 'is_aayah', 'count', 'order'])
        ->map(fn (Thikr $thikr): array => $thikr->toAthkarArray())
        ->all();

    expect($response->json('athkar'))->toBe($expected);

    $first = Thikr::factory()->create(['text' => 'First', 'count' => 1, 'is_aayah' => false]);
    $second = Thikr::factory()->create(['text' => 'Second', 'count' => 1, 'is_aayah' => false]);

    getJson(route('api.athkar.index'))->assertSuccessful();

    $first->update(['text' => 'First (edited)']);
    Thikr::setNewOrder([$second->id, $first->id]);

    $response = getJson(route('api.athkar.index'));

    $response->assertSuccessful();

    expect(
        collect($response->json('athkar'))
            ->whereIn('id', [$first->id, $second->id])
            ->pluck('id')
            ->values()
            ->all(),
    )->toBe([$second->id, $first->id]);

    expect(collect($response->json('athkar'))->firstWhere('id', $first->id)['text'])
        ->toBe('First (edited)');
});

it('normalizes aayah text and computes original-flag semantics from origin values', function () {
    $thikr = Thikr::factory()->create([
        'is_aayah' => true,
        'text' => 'الحمد لله',
    ]);

    expect($thikr->fresh()->text)
        ->toBe(Thikr::AAYAH_OPENING_MARK.'الحمد لله'.Thikr::AAYAH_CLOSING_MARK);

    $thikr->update([
        'is_aayah' => false,
    ]);

    expect($thikr->fresh()->text)->toBe('الحمد لله');

    $original = Thikr::factory()->create([
        'origin' => 'مصدر تجريبي',
        'type' => ThikrType::Supplication,
    ]);

    $nonOriginal = Thikr::factory()->create([
        'origin' => null,
        'type' => ThikrType::Glorification,
    ]);

    expect($original->fresh()->is_original)->toBeTrue()
        ->and($nonOriginal->fresh()->is_original)->toBeFalse();
});

it('rate limits the athkar endpoint', function () {
    RateLimiter::for('athkar', fn (Request $request): Limit => Limit::perMinute(2)->by('test'));

    Thikr::factory()->create();

    getJson(route('api.athkar.index'))->assertSuccessful();
    getJson(route('api.athkar.index'))->assertSuccessful();
    getJson(route('api.athkar.index'))->assertTooManyRequests();
});
