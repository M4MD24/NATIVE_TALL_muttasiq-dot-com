<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;

it('uses php livewire urls when running in native ios runtime', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.platform' => 'ios',
    ]);

    $provider = app()->getProvider(AppServiceProvider::class);
    expect($provider)->not->toBeNull();

    $provider->boot();

    $response = $this->get('/');

    $response->assertOk();

    $html = $response->getContent();

    expect($html)
        ->toContain('/livewire-')
        ->toContain('data-module-url="php://127.0.0.1/livewire-')
        ->toContain('data-update-uri="php://127.0.0.1/livewire-')
        ->not->toContain('http://127.0.0.1/livewire');
});
