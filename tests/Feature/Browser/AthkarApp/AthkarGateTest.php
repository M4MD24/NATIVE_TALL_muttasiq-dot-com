<?php

declare(strict_types=1);

it('navigates to the athkar gate and persists the active view', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarGate($page, false);

    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-gate');
    waitForScript($page, 'JSON.parse(localStorage.getItem("app-active-view"))', 'athkar-app-gate');
    waitForGateVisible($page);

    $page->refresh();

    waitForAlpineReady($page);
    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-gate');
    waitForGateVisible($page);
});

it('native back returns a restored gate view to the main menu before exiting', function () {
    $page = visit('/');

    resetBrowserState($page, true);
    openAthkarGate($page, true);

    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-gate');
    waitForScript($page, 'window.location.hash', '#athkar-app-gate');

    $page->refresh();

    waitForAlpineReady($page);
    enableMobileContext($page);
    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-gate');
    waitForScript($page, 'window.location.hash', '#athkar-app-gate');

    expect($page->script('window.__nativeBackAction()'))->toBeTrue();

    waitForScript($page, homeDataScript('data.activeView'), 'main-menu');
    waitForScript($page, 'window.location.hash', '#main-menu');

    expect($page->script('window.__nativeBackAction()'))->toBeFalse();
    waitForScript($page, homeDataScript('data.activeView'), 'main-menu');
    waitForScript($page, 'window.location.hash', '#main-menu');
});

it('shows the athkar notice and mode hash when selecting a mode', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarGate($page, false);
    $settings = ['does_skip_notice_panels' => false];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);
    openAthkarNotice($page, 'sabah', false);

    waitForScript($page, 'window.location.hash', '#athkar-app-sabah');
    waitForNoticeVisible($page);
});

it('confirms the athkar notice via the CTA button on desktop', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarGate($page, false);
    $settings = ['does_skip_notice_panels' => false];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);
    openAthkarNotice($page, 'sabah', false);
    waitForNoticeVisible($page);

    confirmAthkarNotice($page);

    waitForReaderVisible($page);
});

it('swipes the notice forward and back on desktop', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarGate($page, false);
    $settings = ['does_skip_notice_panels' => false];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);
    openAthkarNotice($page, 'sabah', false);
    waitForNoticeVisible($page);

    swipeNotice($page, 'back', 'mouse');

    waitForScript($page, athkarReaderDataScript('data.isNoticeVisible'), false);
    waitForScript($page, athkarReaderDataScript('data.activeMode'), null);
    waitForGateVisible($page);

    openAthkarNotice($page, 'sabah', false);
    waitForNoticeVisible($page);

    swipeNotice($page, 'forward', 'mouse');

    waitForReaderVisible($page);
});
