<?php

use App\Models\Thikr;
use App\Services\Enums\ThikrType;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

function copyrightVersionShellClasses(string $content): string
{
    $matched = preg_match(
        '/<div\s+class="([^"]*)"\s+data-testid="copyright-version-shell"/',
        $content,
        $matches,
    );

    expect($matched)->toBe(1);

    return $matches[1];
}

function homeMainClasses(string $content): string
{
    $matched = preg_match(
        '/<main\s+class="([^"]*)">/',
        $content,
        $matches,
    );

    expect($matched)->toBe(1);

    return $matches[1];
}

it('fetches athkar from the remote api on mobile', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
        'app.custom.native_end_points.athkar' => 'athkar',
        'app.custom.native_end_points.retries' => 2,
    ]);

    $payload = [
        [
            'id' => 1,
            'time' => 'sabah',
            'type' => ThikrType::Glorification->value,
            'text' => 'Test athkar',
            'origin' => null,
            'is_aayah' => false,
            'is_original' => false,
            'count' => 1,
            'order' => 1,
        ],
    ];

    Http::fake([
        route('api.athkar.index') => Http::response(['athkar' => $payload]),
    ]);

    $response = get('/');

    $response->assertSuccessful();
    $response->assertViewHas('athkar', $payload);

    $shellClasses = copyrightVersionShellClasses($response->getContent());

    expect($shellClasses)
        ->toContain('bottom-7')
        ->not->toContain('bottom-3')
        ->not->toContain('mb-7');

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === route('api.athkar.index');
    });
});

it('uses local athkar payload on non-mobile requests', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'desktop',
    ]);

    Http::fake();

    $thikr = Thikr::factory()->create([
        'type' => ThikrType::Supplication,
        'origin' => 'مرجع',
    ]);

    $response = get('/');

    $response->assertSuccessful();
    $response->assertViewHas('athkar', function (array $athkar) use ($thikr): bool {
        return collect($athkar)->contains(function (array $item) use ($thikr): bool {
            return $item['id'] === $thikr->id
                && $item['type'] === ThikrType::Supplication->value
                && $item['is_original'] === true
                && $item['origin'] === 'مرجع';
        });
    });

    $shellClasses = copyrightVersionShellClasses($response->getContent());

    expect($shellClasses)
        ->toContain('bottom-3')
        ->not->toContain('bottom-7');

    Http::assertNothingSent();
});

it('falls back to local athkar payload on mobile when api request fails', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
        'app.custom.native_end_points.athkar' => 'athkar',
        'app.custom.native_end_points.retries' => 2,
    ]);

    $thikr = Thikr::factory()->create([
        'type' => ThikrType::Protection,
        'origin' => null,
    ]);

    Http::fake([
        route('api.athkar.index') => Http::failedConnection(),
    ]);

    $response = get('/');

    $response->assertSuccessful();
    $response->assertViewHas('athkar', function (array $athkar) use ($thikr): bool {
        return collect($athkar)->contains(function (array $item) use ($thikr): bool {
            return $item['id'] === $thikr->id
                && $item['type'] === ThikrType::Protection->value
                && $item['is_original'] === false
                && $item['origin'] === null;
        });
    });

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === route('api.athkar.index');
    });
});

it('uses local athkar payload on mobile when app url has a non-http scheme', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'ios',
        'app.custom.native_end_points.athkar' => 'athkar',
        'app.url' => 'php://127.0.0.1',
    ]);

    Http::fake();

    $thikr = Thikr::factory()->create([
        'type' => ThikrType::Supplication,
        'origin' => null,
    ]);

    $response = get('/');

    $response->assertSuccessful();
    $response->assertViewHas('athkar', function (array $athkar) use ($thikr): bool {
        return collect($athkar)->contains(function (array $item) use ($thikr): bool {
            return $item['id'] === $thikr->id
                && $item['type'] === ThikrType::Supplication->value
                && $item['is_original'] === false
                && $item['origin'] === null;
        });
    });

    Http::assertNothingSent();
});

it('applies an inset-aware top margin class for ios and keeps default spacing for android', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'ios',
    ]);

    $iosResponse = get('/');

    $iosResponse->assertSuccessful();

    $iosMainClasses = homeMainClasses($iosResponse->getContent());

    expect($iosMainClasses)
        ->toContain('mt-[calc(4rem+max(0px,calc(var(--inset-top,0px)-20px)))]')
        ->not->toContain('mt-16');

    config([
        'nativephp-internal.platform' => 'android',
    ]);

    $androidResponse = get('/');

    $androidResponse->assertSuccessful();

    $androidMainClasses = homeMainClasses($androidResponse->getContent());

    expect($androidMainClasses)
        ->toContain('mt-16')
        ->not->toContain('mt-[calc(4rem+max(0px,calc(var(--inset-top,0px)-20px)))]');
});

it('fetches athkar from the configured absolute endpoint on mobile even with a php app url', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'ios',
        'app.url' => 'php://127.0.0.1',
        'app.custom.native_end_points.athkar' => 'https://muttasiq.com/api/athkar',
        'app.custom.native_end_points.retries' => 2,
    ]);

    $payload = [
        [
            'id' => 1,
            'time' => 'sabah',
            'type' => ThikrType::Glorification->value,
            'text' => 'Remote endpoint athkar',
            'origin' => null,
            'is_aayah' => false,
            'is_original' => false,
            'count' => 1,
            'order' => 1,
        ],
    ];

    Http::fake([
        'https://muttasiq.com/api/athkar' => Http::response(['athkar' => $payload]),
    ]);

    $response = get('/');

    $response->assertSuccessful();
    $response->assertViewHas('athkar', $payload);

    Http::assertSent(function (HttpRequest $request): bool {
        return $request->url() === 'https://muttasiq.com/api/athkar';
    });
});

it('renders the shared origin-indicator icon class without pixel offset hacks', function () {
    $response = get('/');

    $response->assertSuccessful()
        ->assertSee('athkar-origin-indicator__icon', false);

    $content = $response->getContent();

    expect(substr_count($content, 'athkar-origin-indicator__icon'))->toBeGreaterThanOrEqual(2)
        ->and($content)->not->toContain('-left-px -top-px');
});

it('anchors stack action button icons to prevent mobile webkit misalignment', function () {
    $response = get('/');

    $response->assertSuccessful();

    $content = $response->getContent();

    expect($content)
        ->toContain('relative grid h-10 w-10 rotate-45 place-items-center overflow-hidden')
        ->toContain('absolute top-1/2 left-1/2 h-8 w-8 -translate-x-1/2 -translate-y-1/2 -rotate-45 shrink-0');
});
