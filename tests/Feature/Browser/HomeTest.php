<?php

declare(strict_types=1);

it('renders the main menu and shows hover captions', function () {
    $page = visit('/');

    resetBrowserState($page);

    $page->assertScript("document.querySelectorAll('[data-main-menu-item]').length", 9);

    waitForScript($page, mainMenuDataScript('data.isTouchDevice !== null'), true);
    $isTouch = $page->script(mainMenuDataScript('data.isTouchDevice'));

    $captions = [
        'الأذكار',
        'الأدعية',
        'المعروف',
        'السنن',
        'الكتاب',
        'الآثار',
        'التعلم',
        'الدواء',
        'المحفوظات',
    ];

    foreach ($captions as $caption) {
        $selector = "[data-main-menu-item][data-caption=\"{$caption}\"]";

        if ($isTouch) {
            tapMainMenuItem($page, $caption);
        } else {
            $page->hover($selector);
        }

        waitForScript($page, mainMenuDataScript('data.currentCaption'), $caption);
    }
});

it('shows settings and color scheme buttons by default', function () {
    $page = visit('/');

    resetBrowserState($page);

    $page
        ->assertVisible('[data-stack-item][x-data] [data-testid="settings-button"]')
        ->assertVisible('[data-testid="color-scheme-switch-button"]');
});

it('adds the main menu hash on a fresh load', function () {
    $page = visit('/');

    resetBrowserState($page);

    waitForScript($page, 'window.location.hash', '#main-menu');
    waitForScript($page, 'JSON.parse(localStorage.getItem("app-active-view"))', 'main-menu');
});
