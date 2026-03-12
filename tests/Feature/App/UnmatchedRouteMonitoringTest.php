<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;

use function Pest\Laravel\get;

it('returns a 404 response for unmatched web routes', function () {
    get('/route-does-not-exist')->assertNotFound();
});

it('logs repeated unmatched route hits once threshold is reached for an ip address', function () {
    config([
        'app.env' => 'production',
        'nativephp-internal.running' => false,
        'nativephp-internal.platform' => null,
        'app.custom.security.unmatched_routes.window_seconds' => 300,
        'app.custom.security.unmatched_routes.alert_threshold' => 3,
        'app.custom.security.unmatched_routes.alert_repeat_every' => 3,
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Repeated unmatched route requests detected.'
                && $context['ip'] === '203.0.113.10'
                && $context['attempts_in_window'] === 3
                && $context['path'] === '/missing-three';
        });

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get('/missing-one')->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get('/missing-two')->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get('/missing-three')->assertNotFound();
});

it('does not log unmatched route hits outside production', function () {
    config([
        'app.env' => 'local',
        'nativephp-internal.running' => false,
        'nativephp-internal.platform' => null,
        'app.custom.security.unmatched_routes.window_seconds' => 300,
        'app.custom.security.unmatched_routes.alert_threshold' => 1,
        'app.custom.security.unmatched_routes.alert_repeat_every' => 1,
    ]);

    Log::spy();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])->get('/ignored-local')->assertNotFound();

    Log::shouldNotHaveReceived('warning');
});

it('does not log unmatched route hits for non-web platforms', function () {
    config([
        'app.env' => 'production',
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'android',
        'app.custom.security.unmatched_routes.window_seconds' => 300,
        'app.custom.security.unmatched_routes.alert_threshold' => 1,
        'app.custom.security.unmatched_routes.alert_repeat_every' => 1,
    ]);

    Log::spy();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.12'])->get('/ignored-non-web')->assertNotFound();

    Log::shouldNotHaveReceived('warning');
});
