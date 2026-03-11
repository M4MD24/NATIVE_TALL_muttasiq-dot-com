<?php

use App\Models\Setting;
use App\Models\Thikr;
use App\Services\Enums\ThikrType;
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

function buttonsStackClasses(string $content): string
{
    $matched = preg_match(
        '/<div\b[^>]*x-bind:data-respecting-stack=[^>]*>/',
        $content,
        $matches,
    );

    expect($matched)->toBe(1);

    $classMatched = preg_match(
        '/\sclass="([^"]*)"/',
        $matches[0],
        $classMatches,
    );

    if ($classMatched !== 1) {
        return '';
    }

    return $classMatches[1];
}

it('uses local athkar payload on mobile launch and defers remote sync', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
        'app.custom.native_end_points.athkar' => 'athkar',
        'app.custom.native_end_points.settings' => 'settings',
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

    $content = $response->getContent();
    $shellClasses = copyrightVersionShellClasses($content);

    expect($shellClasses)
        ->toContain('bottom-7')
        ->not->toContain('bottom-3')
        ->not->toContain('mb-7');

    expect($content)->toContain('data-testid="startup-sync-shield"')
        ->toContain('data-testid="startup-sync-component"')
        ->toContain('startup-sync-resolved');

    Http::assertNothingSent();
});

it('does not render native startup loader markup during normal mobile launch', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
    ]);

    $response = get('/');

    $response->assertSuccessful()
        ->assertDontSee('data-testid="native-startup-loader"', false);
});

it('resets app version to configured value on mobile launch before deferred sync', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
        'app.custom.app_version' => '3.1.4',
    ]);

    Setting::setAppVersion('0.9.0');

    $response = get('/');

    $response->assertSuccessful()
        ->assertSee('v3.1.4', false);

    expect(Setting::appVersion())->toBe('3.1.4');
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

it('applies ios top margins to stack and main and keeps defaults for android', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'ios',
    ]);

    $iosResponse = get('/');

    $iosResponse->assertSuccessful();

    $iosContent = $iosResponse->getContent();
    $iosMainClasses = homeMainClasses($iosContent);
    $iosStackClasses = buttonsStackClasses($iosContent);

    expect($iosMainClasses)
        ->toContain('mt-22')
        ->not->toContain('mt-16');

    expect($iosStackClasses)->toContain('mt-8');

    config([
        'nativephp-internal.platform' => 'android',
    ]);

    $androidResponse = get('/');

    $androidResponse->assertSuccessful();

    $androidContent = $androidResponse->getContent();
    $androidMainClasses = homeMainClasses($androidContent);
    $androidStackClasses = buttonsStackClasses($androidContent);

    expect($androidMainClasses)
        ->toContain('mt-16')
        ->not->toContain('mt-22');

    expect($androidStackClasses)->not->toContain('mt-8');
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
