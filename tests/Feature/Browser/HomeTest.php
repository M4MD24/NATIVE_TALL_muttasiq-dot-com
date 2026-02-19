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
        ->assertVisible('[data-stack-item][x-data] [data-testid="control-panel-button"]')
        ->assertVisible('[data-testid="color-scheme-switch-button"]');
});

it('uses the dark app icon in settings after toggling dark mode', function () {
    $page = visit('/');

    resetBrowserState($page);
    waitForScript($page, 'Boolean(window.Livewire)', true);

    $page->script("window.Livewire.dispatchTo('color-scheme-switcher', 'color-scheme-toggled', { isDarkModeOn: false });");

    hashAction($page, '#toggle-color-scheme', false);
    waitForScript($page, "Boolean(window.Alpine?.store?.('colorScheme')?.isDarkModeOn)", true);

    openControlPanelModal($page);

    waitForScript($page, <<<'JS'
(() => {
  const icon = document.querySelector('.fi-modal-window img[alt="Muttasiq application icono"]');
  const src = icon?.getAttribute('src');
  return Boolean(src && src.includes('icon-dark.png'));
})()
JS, true);
});

it('cycles the copyright panel and keeps it visible while hovering on desktop', function () {
    $page = visit('/');

    resetBrowserState($page);

    waitForScript($page, <<<'JS'
(() => {
  const shell = document.querySelector('[data-testid="copyright-version-shell"]');
  const data = shell && window.Alpine?.$data ? window.Alpine.$data(shell) : null;
  return Boolean(shell && data && data.isVisible === false);
})()
JS, true);

    $prepared = (bool) $page->script(<<<'JS'
(() => {
  const shell = document.querySelector('[data-testid="copyright-version-shell"]');
  const data = shell && window.Alpine?.$data ? window.Alpine.$data(shell) : null;
  const bp = window.Alpine?.store?.('bp');
  if (!data || !bp) {
    return false;
  }
  bp.hasTouch = false;
  data.waitDuration = 120;
  data.visibleDuration = 80;
  data.queueNextReveal(20);
  return true;
})()
JS);

    expect($prepared)->toBeTrue();

    waitForScript($page, <<<'JS'
(() => {
  const shell = document.querySelector('[data-testid="copyright-version-shell"]');
  const data = shell && window.Alpine?.$data ? window.Alpine.$data(shell) : null;
  return Boolean(data?.isVisible === true);
})()
JS, true);

    waitForScript($page, <<<'JS'
(() => {
  const shell = document.querySelector('[data-testid="copyright-version-shell"]');
  const data = shell && window.Alpine?.$data ? window.Alpine.$data(shell) : null;
  return Boolean(data?.isVisible === false);
})()
JS, true);

    $page->script(<<<'JS'
(() => {
  const shell = document.querySelector('[data-testid="copyright-version-shell"]');
  window.__copyrightHoverAt = Date.now();
  shell?.dispatchEvent(new Event('mouseenter', { bubbles: true }));
  return true;
})()
JS);

    waitForScript($page, <<<'JS'
(() => {
  const shell = document.querySelector('[data-testid="copyright-version-shell"]');
  const data = shell && window.Alpine?.$data ? window.Alpine.$data(shell) : null;
  return Boolean(data?.isVisible === true && (Date.now() - (window.__copyrightHoverAt ?? 0)) >= 170);
})()
JS, true);

    $page->script(<<<'JS'
(() => {
  const shell = document.querySelector('[data-testid="copyright-version-shell"]');
  shell?.dispatchEvent(new Event('mouseleave', { bubbles: true }));
  return true;
})()
JS);

    waitForScript($page, <<<'JS'
(() => {
  const shell = document.querySelector('[data-testid="copyright-version-shell"]');
  const data = shell && window.Alpine?.$data ? window.Alpine.$data(shell) : null;
  return Boolean(data?.isVisible === false);
})()
JS, true);
});

it('opens settings on the updates tab when requested', function () {
    $page = visit('/');

    resetBrowserState($page);

    waitForScript($page, 'Boolean(window.Livewire)', true);

    $page->script('window.dispatchEvent(new CustomEvent("open-control-panel-modal", { detail: { tab: "updates" } }));');

    waitForScript($page, 'Boolean(document.querySelector(".fi-modal-window"))', true);
    waitForScript($page, <<<'JS'
(() => {
  const activeTab = document.querySelector('.fi-modal-window .fi-tabs .fi-tabs-item.fi-active .fi-tabs-item-label');
  if (!activeTab) {
    return false;
  }
  const label = (activeTab.textContent ?? '').replace(/\s+/g, ' ').trim();
  return label.includes('تحديثات');
})()
JS, true);
});

it('opens the updates tab from the version button on touch devices', function () {
    $page = visit('/');

    resetBrowserState($page, true);

    waitForScript($page, 'Boolean(window.Livewire)', true);

    $keptVisible = (bool) $page->script(<<<'JS'
(() => {
  const shell = document.querySelector('[data-testid="copyright-version-shell"]');
  const data = shell && window.Alpine?.$data ? window.Alpine.$data(shell) : null;
  if (!shell || !data) {
    return false;
  }

  data.clearLoopTimers();
  data.isVisible = false;
  data.isTouching = false;
  data.visibleDuration = 300;

  shell.dispatchEvent(new Event('touchstart', { bubbles: true, cancelable: true }));
  shell.dispatchEvent(new Event('touchend', { bubbles: true, cancelable: true }));

  return data.isVisible === true;
})()
JS);

    expect($keptVisible)->toBeTrue();

    $page->click('[data-testid="copyright-version-button"]');

    waitForScript($page, 'Boolean(document.querySelector(".fi-modal-window"))', true);
    waitForScript($page, <<<'JS'
(() => {
  const activeTab = document.querySelector('.fi-modal-window .fi-tabs .fi-tabs-item.fi-active .fi-tabs-item-label');
  if (!activeTab) {
    return false;
  }
  const label = (activeTab.textContent ?? '').replace(/\s+/g, ' ').trim();
  return label.includes('تحديثات');
})()
JS, true);
});

it('adds the main menu hash on a fresh load', function () {
    $page = visit('/');

    resetBrowserState($page);

    waitForScript($page, 'window.location.hash', '#main-menu');
    waitForScript($page, 'JSON.parse(localStorage.getItem("app-active-view"))', 'main-menu');
});

it('stacks only visible quick-stack items when respecting stack mode', function () {
    $page = visit('/');

    resetBrowserState($page);

    $snapshot = null;
    $lastResult = null;

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        /** @var array<string, mixed>|null $result */
        $result = $page->script(<<<'JS'
(() => {
  const bp = window.Alpine?.store?.('bp');
  const stackRoot = document.querySelector('[x-ref="stack"]')?.parentElement ?? null;

  if (!bp || !stackRoot) {
    return { ready: false, reason: !bp ? 'missing-bp' : 'missing-stack-root' };
  }

  document.documentElement.style.setProperty('--breakpoint', 'base');
  bp.current = 'base';
  stackRoot.dataset.respectingStack = 'true';
  window.dispatchEvent(new Event('resize'));

  const allItems = Array.from(document.querySelectorAll('[data-stack-item]'));
  if (allItems.length < 3) {
    return { ready: false, reason: 'not-enough-items', allCount: allItems.length };
  }

  allItems[0].hidden = true;

  const stackData = window.Alpine?.$data
    ? window.Alpine.$data(stackRoot)
    : (stackRoot.__x?.$data ?? null);

  if (!stackData || typeof stackData.setRespectingStack !== 'function' || typeof stackData.updateLayout !== 'function') {
    return { ready: false, reason: 'missing-stack-data' };
  }

  stackData.setRespectingStack();

  if (!stackData.respectingStack) {
    return { ready: false, reason: 'respecting-stack-disabled' };
  }

  stackData.updateLayout();

  const visibleItems = allItems.filter((item) => !item.hidden && getComputedStyle(item).display !== 'none');

  if (visibleItems.length < 2) {
    return { ready: false, reason: 'not-enough-visible-items', visibleCount: visibleItems.length };
  }

  const transforms = visibleItems.map((item) => {
    const value = String(item.style.transform ?? '');
    const match = value.match(/translateX\((-?\d+(?:\.\d+)?)rem\)/);

    return match ? Number(match[1]) : null;
  });

  if (transforms.some((value) => value === null)) {
    return false;
  }

  const maxOffset = Math.max(...transforms.map((value) => Math.abs(value)));
  const expectedMaxOffset = (visibleItems.length - 1) * 1.2;

  return {
    ready: true,
    allCount: allItems.length,
    visibleCount: visibleItems.length,
    hasNullTransforms: transforms.some((value) => value === null),
    maxOffset,
    expectedMaxOffset,
  };
})()
JS);

        $lastResult = $result;

        if (is_array($result) && ($result['ready'] ?? false) === true) {
            $snapshot = $result;

            break;
        }

        usleep(testRetrySleepMicroseconds());
    }

    expect($snapshot)->toBeArray('Last snapshot payload: '.var_export($lastResult, true));
    expect((bool) ($snapshot['ready'] ?? false))->toBeTrue();
    expect((int) ($snapshot['allCount'] ?? 0))->toBeGreaterThan((int) ($snapshot['visibleCount'] ?? 0));
    expect((bool) ($snapshot['hasNullTransforms'] ?? true))->toBeFalse();
    expect((float) ($snapshot['maxOffset'] ?? 0.0))
        ->toBeLessThanOrEqual((float) ($snapshot['expectedMaxOffset'] ?? 0.0) + 0.15);
});

it('recomputes quick-stack positions when visibility changes during a layout pass', function () {
    $page = visit('/');

    resetBrowserState($page, true);

    $prepared = (bool) $page->script(<<<'JS'
(() => {
  const bp = window.Alpine?.store?.('bp');
  const stackRoot = document.querySelector('[x-ref="stack"]')?.parentElement ?? null;

  if (!bp || !stackRoot) {
    return false;
  }

  document.documentElement.style.setProperty('--breakpoint', 'base');
  bp.current = 'base';
  stackRoot.dataset.respectingStack = 'true';
  window.dispatchEvent(new Event('resize'));

  const allItems = Array.from(document.querySelectorAll('[data-stack-item]'));
  if (allItems.length < 4) {
    return false;
  }

  const stackData = window.Alpine?.$data
    ? window.Alpine.$data(stackRoot)
    : (stackRoot.__x?.$data ?? null);

  if (!stackData || typeof stackData.setRespectingStack !== 'function' || typeof stackData.updateLayout !== 'function') {
    return false;
  }

  stackData.setRespectingStack();
  stackData.updateLayout();

  allItems[1].hidden = true;

  return true;
})()
JS);

    expect($prepared)->toBeTrue();

    $snapshot = null;
    $lastResult = null;

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        /** @var array<string, mixed>|null $result */
        $result = $page->script(<<<'JS'
(() => {
  const visibleItems = Array.from(document.querySelectorAll('[data-stack-item]')).filter((item) => {
    const styles = getComputedStyle(item);
    return !item.hidden && styles.display !== 'none' && styles.visibility !== 'hidden';
  });

  if (visibleItems.length < 2) {
    return { ready: false, reason: 'not-enough-visible-items', visibleCount: visibleItems.length };
  }

  const transforms = visibleItems.map((item) => {
    const value = String(item.style.transform ?? '');
    const match = value.match(/translateX\((-?\d+(?:\.\d+)?)rem\)/);

    return match ? Number(match[1]) : null;
  });

  if (transforms.some((value) => value === null)) {
    return { ready: false, reason: 'missing-transform' };
  }

  const maxOffset = Math.max(...transforms.map((value) => Math.abs(value)));
  const expectedMaxOffset = (visibleItems.length - 1) * 1.2;

  return {
    ready: true,
    visibleCount: visibleItems.length,
    maxOffset,
    expectedMaxOffset,
  };
})()
JS);

        $lastResult = $result;

        if (is_array($result) && ($result['ready'] ?? false) === true) {
            $snapshot = $result;

            break;
        }

        usleep(testRetrySleepMicroseconds());
    }

    expect($snapshot)->toBeArray('Last snapshot payload: '.var_export($lastResult, true));
    expect((bool) ($snapshot['ready'] ?? false))->toBeTrue();
    expect((float) ($snapshot['maxOffset'] ?? 0.0))
        ->toBeLessThanOrEqual((float) ($snapshot['expectedMaxOffset'] ?? 0.0) + 0.15);
});

it('can return to the main menu after toggle-color-scheme resets the hash', function () {
    $page = visit('/');

    resetBrowserState($page, true);
    openAthkarGate($page, true);

    hashAction($page, '#toggle-color-scheme', false);

    waitForScript($page, 'window.location.hash === "" || window.location.hash === "#"', true);
    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-gate');

    $clicked = (bool) $page->script(<<<'JS'
(() => {
  const button = document.querySelector("div[data-stack-item][x-show*=\"!views['main-menu']\"] button");
  if (!button) {
    return false;
  }

  button.click();
  button.click();

  return true;
})()
JS);

    expect($clicked)->toBeTrue();

    waitForScript($page, homeDataScript('data.activeView'), 'main-menu');
    waitForScript($page, 'window.location.hash', '#main-menu');
});
