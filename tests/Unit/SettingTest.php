<?php

use App\Models\Setting;

test('athkar setting defaults are available for the home payload', function () {
    $defaults = Setting::defaults();

    expect($defaults)->toBeArray();
    expect(array_key_exists('does_automatically_switch_completed_athkar', $defaults))->toBeTrue();
    expect(array_key_exists('does_clicking_switch_athkar_too', $defaults))->toBeTrue();
    expect(array_key_exists('does_prevent_switching_athkar_until_completion', $defaults))->toBeTrue();
    expect(array_key_exists('does_skip_notice_panels', $defaults))->toBeTrue();
    expect(array_key_exists('minimum_main_text_size', $defaults))->toBeTrue();
    expect(array_key_exists('maximum_main_text_size', $defaults))->toBeTrue();
    expect($defaults['does_skip_notice_panels'])->toBeFalse();
    expect($defaults['minimum_main_text_size'])->toBe(16);
    expect($defaults['maximum_main_text_size'])->toBe(20);
});

test('it normalizes settings payload values by their definitions', function () {
    $normalized = Setting::normalizeSettings([
        'does_skip_notice_panels' => '1',
        'minimum_main_text_size' => 200,
        'maximum_main_text_size' => 200,
    ]);

    expect($normalized['does_skip_notice_panels'])->toBeTrue();
    expect($normalized['minimum_main_text_size'])->toBe(20);
    expect($normalized['maximum_main_text_size'])->toBe(20);

    $normalized = Setting::normalizeSettings([
        'minimum_main_text_size' => 7,
        'maximum_main_text_size' => 7,
    ]);

    expect($normalized['minimum_main_text_size'])->toBe(10);
    expect($normalized['maximum_main_text_size'])->toBe(10);

    $normalized = Setting::normalizeSettings([
        'minimum_main_text_size' => 20,
        'maximum_main_text_size' => 10,
    ]);

    expect($normalized['minimum_main_text_size'])->toBe(10);
    expect($normalized['maximum_main_text_size'])->toBe(10);
});
