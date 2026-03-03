<?php

declare(strict_types=1);

use App\Models\JsErrorReport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\postJson;

function validJsErrorReportPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'user_note' => 'كنت أضغط على زر فتح الأذكار ثم توقفت الصفحة فجأة.',
        'errors' => [[
            'type' => 'error',
            'time' => now()->toIso8601String(),
            'message' => 'Unexpected <b>error</b>',
            'source' => 'http://127.0.0.1:8000/build/app.js',
            'line' => 19,
            'column' => 8,
            'stack' => 'TypeError: foo is undefined',
        ]],
        'context' => [
            'url' => 'http://127.0.0.1:8000/#athkar-app-sabah',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14)',
            'language' => 'ar',
            'platform' => 'android',
        ],
    ], $overrides);
}

it('stores js error reports through the api endpoint', function () {
    RateLimiter::for('js-error-reports', fn (Request $request): Limit => Limit::none());

    $response = postJson(route('api.js-error-reports.store'), validJsErrorReportPayload([
        'user_note' => ' <script>alert(1)</script> علقت الصفحة بعد الضغط ',
    ]));

    $response
        ->assertCreated()
        ->assertJsonStructure(['id', 'message']);

    $report = JsErrorReport::query()->findOrFail((int) $response->json('id'));

    expect($report->user_note)->toBe('alert(1) علقت الصفحة بعد الضغط')
        ->and($report->error_count)->toBe(1)
        ->and($report->first_error_message)->toBe('Unexpected error')
        ->and($report->runtime_platform)->toBe('android')
        ->and($report->errors)->toBeArray()
        ->and($report->errors[0]['message'])->toBe('Unexpected error');
});

it('validates malformed js error report payloads', function () {
    RateLimiter::for('js-error-reports', fn (Request $request): Limit => Limit::none());

    postJson(route('api.js-error-reports.store'), [
        'user_note' => 'قصير',
        'errors' => [],
    ])->assertUnprocessable();
});

it('rate limits js error report submissions', function () {
    RateLimiter::for('js-error-reports', fn (Request $request): Limit => Limit::perMinute(2)->by('test-key'));

    postJson(route('api.js-error-reports.store'), validJsErrorReportPayload())->assertCreated();
    postJson(route('api.js-error-reports.store'), validJsErrorReportPayload())->assertCreated();
    postJson(route('api.js-error-reports.store'), validJsErrorReportPayload())->assertTooManyRequests();
});
