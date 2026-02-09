<?php

declare(strict_types=1);

use App\Models\Thikr;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\getJson;

it('returns cached athkar in order', function () {
    RateLimiter::for('athkar', fn (Request $request): Limit => Limit::none());

    Thikr::factory()->count(3)->create();

    $response = getJson('/api/athkar');

    $response->assertSuccessful();

    $expected = Thikr::query()
        ->ordered()
        ->get(['id', 'time', 'text', 'count', 'order'])
        ->map(fn (Thikr $thikr): array => $thikr->toAthkarArray())
        ->all();

    expect($response->json('athkar'))->toBe($expected);
});

it('rate limits the athkar endpoint', function () {
    RateLimiter::for('athkar', fn (Request $request): Limit => Limit::perMinute(2)->by('test'));

    Thikr::factory()->create();

    getJson('/api/athkar')->assertSuccessful();
    getJson('/api/athkar')->assertSuccessful();
    getJson('/api/athkar')->assertTooManyRequests();
});
