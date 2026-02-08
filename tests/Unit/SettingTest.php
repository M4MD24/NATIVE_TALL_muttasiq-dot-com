<?php

use App\Models\Setting;

test('athkar setting defaults are available for the home payload', function () {
    $defaults = Setting::defaults();

    expect($defaults)->toBeArray();
    expect(array_key_exists('does_automatically_switch_completed_athkar', $defaults))->toBeTrue();
    expect(array_key_exists('does_clicking_switch_athkar_too', $defaults))->toBeTrue();
    expect(array_key_exists('does_prevent_switching_athkar_until_completion', $defaults))->toBeTrue();
    expect(array_key_exists('does_skip_notice_panels', $defaults))->toBeTrue();
    expect($defaults['does_skip_notice_panels'])->toBeFalse();
});
