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
    expect($defaults['minimum_main_text_size'])->toBe(Setting::MIN_MAIN_TEXT_SIZE_DEFAULT);
    expect($defaults['maximum_main_text_size'])->toBe(Setting::MAX_MAIN_TEXT_SIZE_DEFAULT);
});

test('it exposes main text size limits for frontend consumers', function () {
    $limits = Setting::mainTextSizeLimits();

    expect($limits[Setting::MINIMUM_MAIN_TEXT_SIZE])->toBe([
        'min' => Setting::MIN_MAIN_TEXT_SIZE_MIN,
        'max' => Setting::MIN_MAIN_TEXT_SIZE_MAX,
        'default' => Setting::MIN_MAIN_TEXT_SIZE_DEFAULT,
    ]);
    expect($limits[Setting::MAXIMUM_MAIN_TEXT_SIZE])->toBe([
        'min' => Setting::MAX_MAIN_TEXT_SIZE_MIN,
        'max' => Setting::MAX_MAIN_TEXT_SIZE_MAX,
        'default' => Setting::MAX_MAIN_TEXT_SIZE_DEFAULT,
    ]);
});

test('it normalizes settings payload values by their definitions', function () {
    $normalized = Setting::normalizeSettings([
        'does_skip_notice_panels' => '1',
        'minimum_main_text_size' => 200,
        'maximum_main_text_size' => 200,
    ]);

    expect($normalized['does_skip_notice_panels'])->toBeTrue();
    expect($normalized['minimum_main_text_size'])->toBe(Setting::MIN_MAIN_TEXT_SIZE_MAX);
    expect($normalized['maximum_main_text_size'])->toBe(Setting::MAX_MAIN_TEXT_SIZE_MAX);

    $normalized = Setting::normalizeSettings([
        'minimum_main_text_size' => 7,
        'maximum_main_text_size' => 7,
    ]);

    expect($normalized['minimum_main_text_size'])->toBe(Setting::MIN_MAIN_TEXT_SIZE_MIN);
    expect($normalized['maximum_main_text_size'])->toBe(Setting::MAX_MAIN_TEXT_SIZE_MIN);

    $normalized = Setting::normalizeSettings([
        'minimum_main_text_size' => Setting::MIN_MAIN_TEXT_SIZE_MAX,
        'maximum_main_text_size' => Setting::MAX_MAIN_TEXT_SIZE_MIN,
    ]);

    expect($normalized['minimum_main_text_size'])->toBe(Setting::MIN_MAIN_TEXT_SIZE_MIN);
    expect($normalized['maximum_main_text_size'])->toBe(Setting::MAX_MAIN_TEXT_SIZE_MIN);
});
