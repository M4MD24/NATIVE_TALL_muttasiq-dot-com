<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\Thikr;
use App\Services\Enums\ThikrTime;

it('honors auto-advance and overcount settings on tap', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    $settings = [
        'does_automatically_switch_completed_athkar' => true,
        'does_prevent_switching_athkar_until_completion' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    $singleIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item, index) => Number(item.count ?? 1) === 1 && index < data.activeList.length - 1)',
        ),
    );

    expect($singleIndex)->toBeGreaterThanOrEqual(0);

    $page->script(
        athkarReaderCommandScript(
            "data.setActiveIndex({$singleIndex}); data.setCount({$singleIndex}, 0, { allowOvercount: true });",
        ),
    );

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex);

    scriptClick($page, '[data-athkar-slide][data-active="true"] [data-athkar-tap]');

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex + 1);

    $settings = [
        'does_automatically_switch_completed_athkar' => false,
        'does_prevent_switching_athkar_until_completion' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    $page->script(
        athkarReaderCommandScript(
            "data.setActiveIndex({$singleIndex}); data.setCount({$singleIndex}, 0, { allowOvercount: true });",
        ),
    );

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex);

    scriptClick($page, '[data-athkar-slide][data-active="true"] [data-athkar-tap]');
    scriptClick($page, '[data-athkar-slide][data-active="true"] [data-athkar-tap]');

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex);
    waitForScript($page, athkarReaderDataScript('data.countAt(data.activeIndex)'), 2);
});

it('swipes count when setting 2 is enabled', function (bool $isMobile, string $pointerType) {
    $page = $isMobile ? visitMobile('/') : visit('/');

    resetBrowserState($page, $isMobile);
    openAthkarReader($page, 'sabah', $isMobile);

    $settings = [
        'does_clicking_switch_athkar_too' => true,
        'does_automatically_switch_completed_athkar' => true,
        'does_prevent_switching_athkar_until_completion' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    $singleIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item, index) => Number(item.count ?? 1) === 1 && index < data.activeList.length - 1)',
        ),
    );

    expect($singleIndex)->toBeGreaterThanOrEqual(0);

    $page->script(athkarReaderCommandScript("data.setActiveIndex({$singleIndex});"));

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex);

    swipeReader($page, 'forward', $pointerType);

    waitForScript($page, athkarReaderDataScript('data.countAt('.$singleIndex.')'), 1);
    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex + 1);
})->with([
    'desktop' => [false, 'mouse'],
    // 'mobile' => [true, 'touch'],
]);

it('swipes only navigate without counting when setting 2 is disabled', function (bool $isMobile, string $pointerType) {
    $page = $isMobile ? visitMobile('/') : visit('/');

    resetBrowserState($page, $isMobile);
    openAthkarReader($page, 'sabah', $isMobile);

    $settings = [
        'does_clicking_switch_athkar_too' => false,
        'does_prevent_switching_athkar_until_completion' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    $singleIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item, index) => Number(item.count ?? 1) === 1 && index < data.activeList.length - 1)',
        ),
    );

    expect($singleIndex)->toBeGreaterThanOrEqual(0);

    $page->script(
        athkarReaderCommandScript(
            "data.setActiveIndex({$singleIndex}); data.setCount({$singleIndex}, 0, { allowOvercount: true });",
        ),
    );

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex);

    swipeReader($page, 'forward', $pointerType);

    waitForScript($page, athkarReaderDataScript('data.countAt('.$singleIndex.')'), 0);
    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex + 1);
})->with([
    'desktop' => [false, 'mouse'],
    // 'mobile' => [true, 'touch'],
]);

it('treats up and down swipes as forward navigation', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    $settings = [
        'does_clicking_switch_athkar_too' => false,
        'does_prevent_switching_athkar_until_completion' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    waitForScript($page, athkarReaderDataScript('data.activeList.length > 2'), true);

    $page->script(athkarReaderCommandScript('data.setActiveIndex(0);'));
    waitForScript($page, athkarReaderDataScript('data.activeIndex'), 0);

    $page->script(
        athkarReaderCommandScript(<<<'JS'
const panel = document.querySelector('.athkar-panel[role="region"][aria-roledescription="carousel"]');
const rect = panel?.getBoundingClientRect() ?? { left: 0, top: 0, width: 200, height: 400 };
const x = rect.left + (rect.width / 2);
const startY = rect.top + (rect.height * 0.7);
const endY = rect.top + (rect.height * 0.2);
data.swipeStart({ type: 'pointerdown', pointerType: 'touch', clientX: x, clientY: startY, button: 0, target: panel });
data.swipeEnd({ type: 'pointerup', pointerType: 'touch', clientX: x, clientY: endY, button: 0, target: panel });
JS),
    );
    waitForScript($page, athkarReaderDataScript('data.activeIndex'), 1);

    $page->script(
        athkarReaderCommandScript(<<<'JS'
const panel = document.querySelector('.athkar-panel[role="region"][aria-roledescription="carousel"]');
const rect = panel?.getBoundingClientRect() ?? { left: 0, top: 0, width: 200, height: 400 };
const x = rect.left + (rect.width / 2);
const startY = rect.top + (rect.height * 0.3);
const endY = rect.top + (rect.height * 0.8);
data.swipeStart({ type: 'pointerdown', pointerType: 'touch', clientX: x, clientY: startY, button: 0, target: panel });
data.swipeEnd({ type: 'pointerup', pointerType: 'touch', clientX: x, clientY: endY, button: 0, target: panel });
JS),
    );
    waitForScript($page, athkarReaderDataScript('data.activeIndex'), 2);
});

it('prevents swiping past incomplete athkar and allows quick navigation when disabled', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    setAthkarSettings($page, [
        'does_automatically_switch_completed_athkar' => false,
        'does_clicking_switch_athkar_too' => false,
        'does_prevent_switching_athkar_until_completion' => true,
    ]);
    waitForScript($page, athkarReaderDataScript('data.settings.does_prevent_switching_athkar_until_completion'), true);

    swipeReader($page, 'forward', 'mouse');

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), 0);

    $page->script(athkarReaderCommandScript('data.completeThikr(data.activeIndex);'));

    waitForScript(
        $page,
        athkarReaderDataScript('data.isItemComplete(data.activeIndex)'),
        true,
    );

    swipeReader($page, 'forward', 'mouse');

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), 1);

    $settings = [
        'does_automatically_switch_completed_athkar' => false,
        'does_prevent_switching_athkar_until_completion' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    $page->script(
        athkarReaderCommandScript('data.setActiveIndex(data.activeList.length - 1);'),
    );

    $lastIndex = $page->script(athkarReaderDataScript('data.activeList.length - 1'));

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $lastIndex);
});

it('persists athkar counts, overcounts, and restores the reader on reload', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    setAthkarSettings($page, [
        'does_automatically_switch_completed_athkar' => false,
        'does_prevent_switching_athkar_until_completion' => false,
    ]);

    $singleIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item, index) => Number(item.count ?? 1) === 1 && index < data.activeList.length - 1)',
        ),
    );

    expect($singleIndex)->toBeGreaterThanOrEqual(0);
    $targetItemId = $page->script(athkarReaderDataScript('data.activeList['.$singleIndex.']?.id ?? null'));
    expect($targetItemId)->not->toBeNull();

    $page->script(athkarReaderCommandScript("data.setActiveIndex({$singleIndex});"));

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex);

    scriptClick($page, '[data-athkar-slide][data-active="true"] [data-athkar-tap]');
    scriptClick($page, '[data-athkar-slide][data-active="true"] [data-athkar-tap]');

    waitForScript($page, athkarReaderDataScript('data.countAt(data.activeIndex)'), 2);

    $progress = $page->script('JSON.parse(localStorage.getItem("athkar-progress-v1"))');

    expect($progress['sabah']['counts'][$singleIndex] ?? null)->toBe(2);

    waitForScript($page, 'JSON.parse(localStorage.getItem("athkar-active-mode"))', 'sabah');
    waitForScript($page, 'JSON.parse(localStorage.getItem("athkar-reader-visible"))', true);
    waitForScript($page, 'JSON.parse(localStorage.getItem("app-active-view"))', 'athkar-app-sabah');
    waitForScript($page, 'window.location.hash', '#athkar-app-sabah');
    $todayKey = $page->script(<<<'JS'
(() => {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
})()
JS);
    $page->script(athkarReaderCommandScript('data.lastSeenDay = data.todayKey();'));
    setLocalStorageValue($page, 'athkar-last-day', $todayKey);
    waitForScript($page, 'JSON.parse(localStorage.getItem("athkar-last-day"))', $todayKey);

    $page->refresh();

    waitForAlpineReady($page);
    ensureAthkarReaderMode($page, 'sabah');
    $targetItemIdExpression = js_encode($targetItemId);
    waitForScriptWithTimeout(
        $page,
        athkarReaderDataScript(
            "data.activeList.some((item) => String(item?.id ?? '') === String({$targetItemIdExpression}))",
        ),
        true,
        4_000,
    );
    $restoredIndex = $page->script(
        athkarReaderDataScript(
            "data.activeList.findIndex((item) => String(item?.id ?? '') === String({$targetItemIdExpression}))",
        ),
    );
    expect($restoredIndex)->toBeGreaterThanOrEqual(0);
    waitForScriptWithTimeout(
        $page,
        js_template(<<<'JS'
(() => {
  const targetId = String({{targetId}});
  const progress = JSON.parse(localStorage.getItem('athkar-progress-v1') ?? '{}');
  const ids = progress?.sabah?.ids ?? [];
  const counts = progress?.sabah?.counts ?? [];
  const targetIndex = ids.findIndex((id) => String(id ?? '') === targetId);
  if (targetIndex < 0) {
    return null;
  }
  return Number(counts[targetIndex] ?? 0);
})()
JS, ['targetId' => $targetItemId]),
        2,
        4_000,
    );
});

it('keeps progress pinned to the same thikr id after add/remove/reorder overrides and reload', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    setAthkarSettings($page, [
        'does_automatically_switch_completed_athkar' => false,
        'does_prevent_switching_athkar_until_completion' => false,
    ]);

    $activeCount = $page->script(athkarReaderDataScript('data.activeList.length'));
    expect($activeCount)->toBeGreaterThan(2);

    $targetIndex = $page->script(athkarReaderDataScript('Math.min(2, data.activeList.length - 1)'));
    expect($targetIndex)->toBeGreaterThanOrEqual(0);

    $targetId = $page->script(athkarReaderDataScript('data.activeList['.$targetIndex.']?.id ?? null'));
    expect($targetId)->not->toBeNull();

    $page->script(
        athkarReaderCommandScript(
            "data.setActiveIndex({$targetIndex}); data.setCount({$targetIndex}, 2, { allowOvercount: true });",
        ),
    );

    waitForScript($page, athkarReaderDataScript('data.countAt(data.activeIndex)'), 2);

    $result = $page->script(js_template(<<<'JS'
(() => {
  const el = document.querySelector('[x-data^="athkarAppReader"]');
  if (!el || !window.Alpine) {
    return null;
  }
  const data = window.Alpine.$data ? window.Alpine.$data(el) : (el.__x?.$data ?? null);
  if (!data) {
    return null;
  }

  const targetId = Number({{targetId}});
  const list = Array.isArray(data.activeList) ? data.activeList : [];
  const deleteCandidate = list.find((item) => Number(item?.id ?? 0) !== targetId);
  const maxExistingId = list.reduce((max, item) => Math.max(max, Number(item?.id ?? 0)), 0);
  const customId = maxExistingId + 1000;

  const overrides = [
    {
      thikr_id: targetId,
      order: 1,
    },
    {
      thikr_id: customId,
      order: 2,
      time: 'shared',
      type: 'supplication',
      text: 'ذكر مخصص لاختبار الاستعادة',
      origin: null,
      count: 1,
      is_aayah: false,
      is_deleted: false,
      is_custom: true,
    },
  ];

  if (deleteCandidate) {
    overrides.push({
      thikr_id: Number(deleteCandidate.id),
      is_deleted: true,
    });
  }

  data.applyAthkarOverrides(overrides, { persist: true });

  return {
    deletedId: deleteCandidate ? Number(deleteCandidate.id) : null,
    customId,
  };
})()
JS, ['targetId' => $targetId]));

    expect($result)->toBeArray();
    $deletedId = $result['deletedId'] ?? null;
    $customId = $result['customId'] ?? null;

    $targetIdExpression = js_encode($targetId);
    waitForScriptWithTimeout(
        $page,
        athkarReaderDataScript(
            "String(data.activeList[data.activeIndex]?.id ?? '') === String({$targetIdExpression})",
        ),
        true,
        4_000,
    );
    waitForScript($page, athkarReaderDataScript('data.countAt(data.activeIndex)'), 2);

    if ($deletedId !== null) {
        $deletedIdExpression = js_encode($deletedId);
        waitForScriptWithTimeout(
            $page,
            athkarReaderDataScript(
                "data.activeList.every((item) => String(item?.id ?? '') !== String({$deletedIdExpression}))",
            ),
            true,
            4_000,
        );
    }

    if ($customId !== null) {
        $customIdExpression = js_encode($customId);
        waitForScriptWithTimeout(
            $page,
            athkarReaderDataScript(
                "data.activeList.some((item) => String(item?.id ?? '') === String({$customIdExpression}))",
            ),
            true,
            4_000,
        );
    }

    forceHomeView($page, 'athkar-app-sabah');
    setHashOnly($page, '#athkar-app-sabah', true, true);
    $page->script(homeDataCommandScript(<<<'JS'
views['athkar-app-gate'].isReaderVisible = true;
JS));
    setLocalStorageValue($page, 'athkar-active-mode', 'sabah');
    setLocalStorageValue($page, 'athkar-reader-visible', true);
    setLocalStorageValue($page, 'app-active-view', 'athkar-app-sabah');
    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-sabah');
    waitForReaderVisible($page);
    waitForScript($page, 'JSON.parse(localStorage.getItem("athkar-active-mode"))', 'sabah');
    waitForScript($page, 'JSON.parse(localStorage.getItem("app-active-view"))', 'athkar-app-sabah');
    waitForScript($page, 'window.location.hash', '#athkar-app-sabah');

    $todayKey = $page->script(<<<'JS'
(() => {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
})()
JS);
    $page->script(athkarReaderCommandScript('data.lastSeenDay = data.todayKey();'));
    setLocalStorageValue($page, 'athkar-last-day', $todayKey);
    waitForScript($page, 'JSON.parse(localStorage.getItem("athkar-last-day"))', $todayKey);

    $page->refresh();

    waitForAlpineReady($page);
    ensureAthkarReaderMode($page, 'sabah');
    waitForScriptWithTimeout(
        $page,
        athkarReaderDataScript(
            "String(data.activeList[data.activeIndex]?.id ?? '') === String({$targetIdExpression})",
        ),
        true,
        4_000,
    );
    waitForScriptWithTimeout(
        $page,
        js_template(<<<'JS'
(() => {
  const targetId = String({{targetId}});
  const progress = JSON.parse(localStorage.getItem('athkar-progress-v1') ?? '{}');
  const ids = progress?.sabah?.ids ?? [];
  const counts = progress?.sabah?.counts ?? [];
  const targetIndex = ids.findIndex((id) => String(id ?? '') === targetId);
  if (targetIndex < 0) {
    return null;
  }
  return Number(counts[targetIndex] ?? 0);
})()
JS, ['targetId' => $targetId]),
        2,
        4_000,
    );
});

it('returns to gate then opens athkar manager from the reader top mode button', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    waitForReaderVisible($page);

    safeClick($page, '[data-athkar-open-manager]');

    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-gate');
    waitForScript($page, 'window.location.hash', '#athkar-app-gate');
    waitForScriptWithTimeout($page, 'Boolean(document.querySelector(".fi-modal-window"))', true, 5_000);
    waitForScript(
        $page,
        <<<'JS'
(() => {
  const managerGrid = document.querySelector('[wire\\:sort="reorderAthkar"]');
  if (!managerGrid) {
    return false;
  }

  return getComputedStyle(managerGrid).display !== 'none';
})()
JS,
        true,
    );
    waitForScriptWithTimeout(
        $page,
        <<<'JS'
(() => {
  const bp = window.Alpine?.store?.('bp');
  const firstCard = document.querySelector('[data-athkar-manager-card]');
  if (!bp || !firstCard) {
    return false;
  }

  return bp.shouldUseSortHandles() === false && firstCard.hasAttribute('wire:sort:handle');
})()
JS,
        true,
        5_000,
    );
});

it('opens athkar manager as a modal on touch layouts and limits drag handles to order and drag controls', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    enableTabletContext($page);
    waitForReaderVisible($page);
    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-sabah');

    safeClick($page, '[data-athkar-open-manager]');

    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-gate');
    waitForScript($page, 'window.location.hash', '#athkar-app-gate');
    waitForScriptWithTimeout($page, 'Boolean(document.querySelector(".fi-modal-window"))', true, 5_000);
    waitForScript(
        $page,
        <<<'JS'
(() => Boolean(document.querySelector('[data-athkar-manager-card] .athkar-manager-card__drag-handle[title="اسحب لإعادة الترتيب"]')))()
JS,
        true,
    );
    waitForScript(
        $page,
        <<<'JS'
(() => Boolean(document.querySelector('[data-athkar-manager-card][wire\\:sort\\:item]')))()
JS,
        true,
    );
    waitForScriptWithTimeout(
        $page,
        <<<'JS'
(() => {
  const bp = window.Alpine?.store?.('bp');
  if (!bp) {
    return false;
  }

  return bp.isTablet() === true && bp.shouldUseSortHandles() === true;
})()
JS,
        true,
        5_000,
    );
    waitForScriptWithTimeout(
        $page,
        <<<'JS'
(() => {
  const card = document.querySelector('[data-athkar-manager-card]');
  if (!card) {
    return false;
  }

  const orderHandle = card.querySelector('.athkar-manager-card__badge--order[data-athkar-sort-handle][wire\\:sort\\:handle]');
  const dragHandle = card.querySelector('.athkar-manager-card__drag-handle[data-athkar-sort-handle][wire\\:sort\\:handle][title="اسحب لإعادة الترتيب"]');

  return !card.hasAttribute('wire:sort:handle') && Boolean(orderHandle) && Boolean(dragHandle);
})()
JS,
        true,
        5_000,
    );
    waitForScriptWithTimeout(
        $page,
        <<<'JS'
(() => {
  const card = document.querySelector('[data-athkar-manager-card]');
  const dragHandle = card?.querySelector('.athkar-manager-card__drag-handle[title="اسحب لإعادة الترتيب"]');
  const orderHandle = card?.querySelector('.athkar-manager-card__badge--order[data-athkar-sort-handle]');
  if (!card || !dragHandle || !orderHandle) {
    return false;
  }

  const cardStyles = getComputedStyle(card);
  const dragStyles = getComputedStyle(dragHandle);
  const orderStyles = getComputedStyle(orderHandle);

  return !String(cardStyles.transitionProperty).includes('transform')
    && dragStyles.touchAction === 'none'
    && orderStyles.touchAction === 'none';
})()
JS,
        true,
        5_000,
    );
});

it('preserves athkar manager scroll after opening and closing a card modal', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    safeClick($page, '[data-athkar-open-manager]');

    waitForScriptWithTimeout($page, 'Boolean(document.querySelector(".fi-modal-window"))', true, 5_000);
    waitForScript($page, 'Boolean(document.querySelector("[data-athkar-manager-card]"))', true);

    $page->script(<<<'JS'
(() => {
  const managerContent = document.querySelector('.fi-modal.fi-modal-open .fi-modal-content');
  const firstCard = document.querySelector('[data-athkar-manager-card]');
  if (!managerContent || !firstCard) {
    return false;
  }

  const target = Math.max(220, Math.round(managerContent.scrollHeight * 0.4));
  managerContent.scrollTop = target;
  window.__athkarManagerScrollBefore = managerContent.scrollTop;

  firstCard.click();
  return true;
})()
JS);

    waitForScriptWithTimeout(
        $page,
        'document.querySelectorAll(".fi-modal.fi-modal-open").length >= 2',
        true,
        5_000,
    );

    $page->script(<<<'JS'
(() => {
  const openModals = Array.from(document.querySelectorAll('.fi-modal.fi-modal-open'));
  const childModal = openModals.at(-1);

  if (!childModal) {
    return false;
  }

  const closeButton = childModal.querySelector('.fi-modal-close-btn, button[aria-label="Close"], button[aria-label="إغلاق"]');

  if (closeButton instanceof HTMLElement) {
    closeButton.click();
    return true;
  }

  const cancelButton = Array.from(childModal.querySelectorAll('button')).find((button) => {
    const label = String(button.textContent ?? '').trim();

    return ['إلغاء', 'Cancel', 'Close', 'إغلاق'].includes(label);
  });

  if (cancelButton instanceof HTMLElement) {
    cancelButton.click();
    return true;
  }

  window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  return true;
})()
JS);

    waitForScriptWithTimeout(
        $page,
        'document.querySelectorAll(".fi-modal.fi-modal-open").length <= 1',
        true,
        5_000,
    );
    waitForScriptWithTimeout($page, 'Boolean(document.querySelector("[data-athkar-manager-card]"))', true, 5_000);

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const managerContent = document.querySelector('.fi-modal.fi-modal-open .fi-modal-content');
  const before = Number(window.__athkarManagerScrollBefore ?? -1);

  if (!managerContent || !Number.isFinite(before) || before < 0) {
    return false;
  }

  return Math.abs(managerContent.scrollTop - before) <= 24;
})()
JS,
        true,
    );
});

it('fits origin text independently and keeps the text box clear of mobile top controls', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    enableMobileContext($page);
    waitForReaderVisible($page);

    $originIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item) => String(item?.origin ?? "").trim().length > 0 || Boolean(item?.is_original))',
        ),
    );

    expect($originIndex)->toBeGreaterThanOrEqual(0);

    $page->script(athkarReaderCommandScript(js_template(<<<'JS'
data.setActiveIndex({{index}});
const activeIndex = data.activeIndex;

if (!data.activeList?.[activeIndex]) {
  return;
}

data.activeList[activeIndex].text = 'لا إله إلا الله';
data.activeList[activeIndex].origin = 'حدثنا عبد الله بن مسلمة عن مالك عن سمي عن أبي صالح عن أبي هريرة رضي الله عنه أن رسول الله صلى الله عليه وسلم قال من قال لا إله إلا الله وحده لا شريك له له الملك وله الحمد وهو على كل شيء قدير في يوم مائة مرة كانت له عدل عشر رقاب وكتبت له مائة حسنة ومحيت عنه مائة سيئة وكانت له حرزا من الشيطان يومه ذلك حتى يمسي';
data.activeList[activeIndex].count = 55;

if (Array.isArray(data.progress?.[data.activeMode]?.counts)) {
  data.progress[data.activeMode].counts[activeIndex] = 0;
}

data.originToggle = { mode: data.activeMode, index: activeIndex };
data.queueReaderTextFit();
JS, ['index' => $originIndex])));

    waitForScript($page, athkarReaderDataScript('data.isOriginVisible(data.activeIndex)'), true);
    waitForScript(
        $page,
        <<<'JS'
(() => {
  const origin = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-origin-text]');
  if (!origin) {
    return false;
  }

  return String(origin.textContent ?? '').trim().length > 150;
})()
JS,
        true,
    );
    $page->script(athkarReaderCommandScript('data.queueReaderTextFit();'));
    waitForScript(
        $page,
        <<<'JS'
(() => {
  const slide = document.querySelector('[data-athkar-slide][data-active="true"]');
  if (!slide) {
    return false;
  }

  const text = slide.querySelector('[data-athkar-text]');
  const origin = slide.querySelector('[data-athkar-origin-text]');
  if (!text || !origin) {
    return false;
  }

  return text.classList.contains('is-fit') && origin.classList.contains('is-fit');
})()
JS,
        true,
    );

    $fontSizes = $page->script(<<<'JS'
(() => {
  const slide = document.querySelector('[data-athkar-slide][data-active="true"]');
  if (!slide) {
    return null;
  }

  const text = slide.querySelector('[data-athkar-text]');
  const origin = slide.querySelector('[data-athkar-origin-text]');
  if (!text || !origin) {
    return null;
  }

  return {
    text: Number.parseFloat(getComputedStyle(text).fontSize),
    origin: Number.parseFloat(getComputedStyle(origin).fontSize),
  };
})()
JS);

    expect($fontSizes)->toBeArray()
        ->and($fontSizes['origin'])->toBeLessThanOrEqual($fontSizes['text']);

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const originIcon = document.querySelector('[data-athkar-slide][data-active="true"] .athkar-origin-indicator--mobile .athkar-origin-indicator__icon');
  if (!originIcon) {
    return false;
  }

  const iconClassName = String(originIcon.className ?? '');

  return (
    originIcon.classList.contains('athkar-origin-indicator__icon') &&
    !iconClassName.includes('-left-px') &&
    !iconClassName.includes('-top-px')
  );
})()
JS,
        true,
    );

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const slide = document.querySelector('[data-athkar-slide][data-active="true"]');
  if (!slide) {
    return false;
  }

  const box = slide.querySelector('[data-athkar-text-box]');
  const counter = slide.querySelector('[data-athkar-mobile-counter] button[aria-label="العدد"]');
  const originToggle = slide.querySelector('.athkar-origin-indicator--mobile');

  if (!box || !counter || !originToggle) {
    return false;
  }

  const controlsBottom = Math.max(
    counter.getBoundingClientRect().bottom,
    originToggle.getBoundingClientRect().bottom,
  );
  const boxRect = box.getBoundingClientRect();
  const paddingTop = Number.parseFloat(getComputedStyle(box).paddingTop);
  const contentTop = boxRect.top + (Number.isFinite(paddingTop) ? paddingTop : 0);

  return contentTop >= (controlsBottom + 4);
})()
JS,
        true,
    );
});

it('re-fits active thikr and origin text immediately when max main text size changes with a fixed min size', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    waitForReaderVisible($page);

    $page->script(athkarReaderCommandScript(<<<'JS'
const index = data.activeIndex;
if (!data.activeList?.[index]) {
  return;
}

data.activeList[index].text = 'لا إله إلا الله وحده لا شريك له';
data.activeList[index].origin = 'رواه البخاري';
data.activeList[index].count = 1;
if (Array.isArray(data.progress?.[data.activeMode]?.counts)) {
  data.progress[data.activeMode].counts[index] = 0;
}
data.originToggle = { mode: data.activeMode, index };
data.queueReaderTextFit();
JS));

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const text = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-text]');
  if (!text) {
    return false;
  }

  const size = Number.parseFloat(getComputedStyle(text).fontSize);

  return Number.isFinite(size) && size > 0;
})()
JS,
        true,
    );

    setAthkarSettings($page, [
        'minimum_main_text_size' => 16,
        'maximum_main_text_size' => 16,
    ]);
    waitForAthkarSettings($page, [
        'minimum_main_text_size' => 16,
        'maximum_main_text_size' => 16,
    ]);
    waitForScript(
        $page,
        <<<'JS'
(() => {
  const text = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-text]');
  const origin = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-origin-text]');
  if (!text || !origin) {
    return null;
  }

  const textSize = Number.parseFloat(getComputedStyle(text).fontSize);
  const originSize = Number.parseFloat(getComputedStyle(origin).fontSize);

  return Number.isFinite(textSize) &&
    Number.isFinite(originSize) &&
    textSize > 0 &&
    originSize > 0 &&
    textSize <= 16.5 &&
    originSize <= 16.5;
})()
JS);

    $fontAtMaxSixteen = $page->script(<<<'JS'
(() => {
  const text = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-text]');
  if (!text) {
    return null;
  }

  return Number.parseFloat(getComputedStyle(text).fontSize);
})()
JS);

    $originFontAtMaxSixteen = $page->script(<<<'JS'
(() => {
  const origin = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-origin-text]');
  if (!origin) {
    return null;
  }

  return Number.parseFloat(getComputedStyle(origin).fontSize);
})()
JS);

    expect($fontAtMaxSixteen)->toBeNumeric()->toBeGreaterThan(0);
    expect($originFontAtMaxSixteen)->toBeNumeric()->toBeGreaterThan(0);

    setAthkarSettings($page, ['maximum_main_text_size' => 20]);
    waitForAthkarSettings($page, ['maximum_main_text_size' => 20]);

    waitForScript(
        $page,
        js_template(
            <<<'JS'
(() => {
  const text = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-text]');
  if (!text) {
    return false;
  }

  const size = Number.parseFloat(getComputedStyle(text).fontSize);

  return Number.isFinite(size) && size >= Number({{min}});
})()
JS,
            ['min' => $fontAtMaxSixteen + 0.5],
        ),
        true,
    );

    waitForScript(
        $page,
        js_template(
            <<<'JS'
(() => {
  const origin = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-origin-text]');
  if (!origin) {
    return false;
  }

  const size = Number.parseFloat(getComputedStyle(origin).fontSize);

  return Number.isFinite(size) && size >= Number({{min}});
})()
JS,
            ['min' => $originFontAtMaxSixteen + 0.5],
        ),
        true,
    );
});

it('restores the notice on reload and allows continuing to the reader when notice panels are enabled', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    $settings = [
        'does_skip_notice_panels' => false,
        'does_prevent_switching_athkar_until_completion' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    waitForReaderVisible($page);
    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-sabah');
    waitForScript($page, athkarReaderDataScript('data.activeMode'), 'sabah');

    $page->refresh();

    waitForAlpineReady($page);
    waitForScript($page, athkarReaderDataScript('data.settings.does_skip_notice_panels'), false);
    waitForNoticeVisible($page);

    confirmAthkarNotice($page);

    waitForReaderVisible($page);
    waitForScript($page, athkarReaderDataScript('data.activeMode'), 'sabah');
});

it('locks completed modes on the gate unless setting 3 is disabled', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    $settings = [
        'does_skip_notice_panels' => true,
        'does_prevent_switching_athkar_until_completion' => true,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    $page->script(athkarReaderCommandScript('data.markAllActiveModeComplete();'));

    waitForGateVisible($page);
    waitForScript($page, athkarReaderDataScript('data.activeMode'), null);

    waitForScript($page, athkarReaderDataScript('data.isModeComplete("sabah")'), true);
    waitForScript($page, athkarReaderDataScript('data.isModeLocked("sabah")'), true);

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const badge = document.querySelector('button[aria-label="أذكار الصباح"] [x-show="isModeComplete(\'sabah\')"]');
  if (!badge) {
    return false;
  }
  return getComputedStyle(badge).display !== 'none';
})()
JS,
        true,
    );

    waitForScript(
        $page,
        'window.location.hash === "#athkar-app-gate" || window.location.hash === ""',
        true,
    );

    scriptClick($page, 'button[aria-label="أذكار الصباح"]');

    waitForScript(
        $page,
        'window.location.hash === "#athkar-app-gate" || window.location.hash === ""',
        true,
    );

    $settings = [
        'does_prevent_switching_athkar_until_completion' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    waitForScript($page, athkarReaderDataScript('data.isModeLocked("sabah")'), false);

    scriptClick($page, 'button[aria-label="أذكار الصباح"]');

    waitForScript($page, 'window.location.hash', '#athkar-app-sabah');
    waitForNoticeVisible($page);
});

it('bypasses hint and completion popups when setting 4 is enabled', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    $settings = [
        'does_skip_notice_panels' => true,
        'does_prevent_switching_athkar_until_completion' => false,
        'does_automatically_switch_completed_athkar' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    $multiIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item) => Number(item.count ?? 1) > 1)',
        ),
    );

    expect($multiIndex)->toBeGreaterThanOrEqual(0);

    $page->script(
        athkarReaderCommandScript(
            "data.setActiveIndex({$multiIndex}); data.setCount({$multiIndex}, 0);",
        ),
    );

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $multiIndex);

    $page->script(athkarReaderCommandScript("data.toggleHint({$multiIndex});"));
    waitForScript($page, athkarReaderDataScript('data.hintIndex'), null);

    $page->script(
        athkarReaderCommandScript("data.requestSingleThikrCompletion({$multiIndex});"),
    );

    waitForScript(
        $page,
        athkarReaderDataScript(
            "data.countAt({$multiIndex}) === data.requiredCount({$multiIndex})",
        ),
        true,
    );
    waitForScript($page, 'Boolean(document.querySelector(".fi-modal-window"))', false);
});

it('expands the mobile counter hint when tapped while hint bypass is disabled', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    enableMobileContext($page);
    waitForReaderVisible($page);

    $settings = [
        'does_skip_notice_panels' => false,
        'does_prevent_switching_athkar_until_completion' => false,
        'does_automatically_switch_completed_athkar' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    $multiIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item) => Number(item.count ?? 1) > 1)',
        ),
    );

    expect($multiIndex)->toBeGreaterThanOrEqual(0);

    $page->script(athkarReaderCommandScript("data.setActiveIndex({$multiIndex}); data.closeHint();"));
    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $multiIndex);

    $mobileCounterSelector = '[data-athkar-slide][data-active="true"] [data-athkar-mobile-counter] button[aria-label="العدد"]';

    waitForScript(
        $page,
        js_template(
            <<<'JS'
(() => {
  const button = document.querySelector({{selector}});
  if (!button) {
    return false;
  }

  const styles = getComputedStyle(button);
  return styles.pointerEvents !== 'none' && styles.opacity !== '0';
})()
JS,
            ['selector' => $mobileCounterSelector],
        ),
        true,
    );

    scriptClick($page, $mobileCounterSelector);
    waitForScript($page, athkarReaderDataScript('data.hintIndex'), $multiIndex);
    waitForScript(
        $page,
        js_template(
            <<<'JS'
(() => {
  const button = document.querySelector({{selector}});
  if (!button) {
    return false;
  }

  return button.getAttribute('aria-expanded') === 'true' && button.getBoundingClientRect().width >= 56;
})()
JS,
            ['selector' => $mobileCounterSelector],
        ),
        true,
    );
});

it('executes hidden completion buttons on desktop for single thikr and all athkar', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    setAthkarSettings($page, [
        'does_prevent_switching_athkar_until_completion' => false,
    ]);

    $multiIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item) => Number(item.count ?? 1) > 1)',
        ),
    );

    expect($multiIndex)->toBeGreaterThanOrEqual(0);

    $page->script(athkarReaderCommandScript("data.setActiveIndex({$multiIndex});"));

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $multiIndex);

    $desktopCompleteSelector = '[data-athkar-slide][data-active="true"] .sm\\:flex button[aria-label="إتمام الذكر"]';
    waitForScript(
        $page,
        js_template('Boolean(document.querySelector({{selector}}))', ['selector' => $desktopCompleteSelector]),
        true,
    );
    scriptClick($page, $desktopCompleteSelector);

    waitForScript($page, 'Boolean(document.querySelector(".fi-modal-window"))', true);
    clickModalAction($page, 'نعم، أكملت قراءته');

    waitForScript(
        $page,
        athkarReaderDataScript('data.countAt('.$multiIndex.') === data.requiredCount('.$multiIndex.')'),
        true,
    );

    $page->script(athkarReaderCommandScript('data.showCompletionHack({ pinned: true })'));

    waitForScript($page, athkarReaderDataScript('data.completionHack.isVisible'), true);

    safeClick($page, 'button[aria-label="إتمام جميع الأذكار"]');

    waitForScript($page, 'Boolean(document.querySelector(".fi-modal-window"))', true);

    clickModalAction($page, 'قرأتها');

    waitForScript($page, athkarReaderDataScript('data.isModeComplete("sabah")'), true);
    waitForScript($page, athkarReaderDataScript('data.activeMode'), null);
});

it('keeps overflowing mobile origin anchored to the top and scrollable', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    enableMobileContext($page);
    waitForReaderVisible($page);

    $originIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item) => String(item?.origin ?? "").trim().length > 0 || Boolean(item?.is_original))',
        ),
    );

    expect($originIndex)->toBeGreaterThanOrEqual(0);

    $page->script(athkarReaderCommandScript(js_template(<<<'JS'
data.setActiveIndex({{index}});
const activeIndex = data.activeIndex;

if (!data.activeList?.[activeIndex]) {
  return;
}

data.activeList[activeIndex].text = 'لا إله إلا الله وحده لا شريك له له الملك وله الحمد';
data.activeList[activeIndex].origin = Array.from(
  { length: 140 },
  () => 'حدثنا عبد الله بن مسلمة عن مالك عن سمي عن أبي صالح عن أبي هريرة رضي الله عنه'
).join(' ');
data.originToggle = { mode: data.activeMode, index: activeIndex };
data.queueReaderTextFit();
JS, ['index' => $originIndex])));

    waitForScript($page, athkarReaderDataScript('data.isOriginVisible(data.activeIndex)'), true);

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const slide = document.querySelector('[data-athkar-slide][data-active="true"]');
  const box = slide?.querySelector('[data-athkar-text-box]');
  const origin = slide?.querySelector('[data-athkar-origin-text]');

  if (!box || !origin) {
    return false;
  }

  if (String(origin.textContent ?? '').trim().length < 200) {
    return false;
  }

  const styles = getComputedStyle(box);

  if (
    box.dataset.athkarOriginOverflow !== 'true' ||
    !box.classList.contains('athkar-text-box--touch-scroll') ||
    !box.classList.contains('athkar-text-box--origin-scroll') ||
    styles.overflowY !== 'auto'
  ) {
    return false;
  }

  box.scrollTop = 0;
  const boxRect = box.getBoundingClientRect();
  const paddingTop = Number.parseFloat(styles.paddingTop) || 0;
  const contentTop = boxRect.top + paddingTop;
  const originRect = origin.getBoundingClientRect();

  if (originRect.top < contentTop - 2) {
    return false;
  }

  box.scrollTop = Math.max(0, box.scrollHeight - box.clientHeight - 8);
  if (box.scrollTop <= 0) {
    return false;
  }

  box.scrollTop = 0;

  return box.scrollTop === 0;
})()
JS,
        true,
    );
});

it('shows single-thikr completion button on touch tablets without hover', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    enableTabletContext($page);
    waitForReaderVisible($page);

    setAthkarSettings($page, [
        'does_prevent_switching_athkar_until_completion' => false,
    ]);

    $multiIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item) => Number(item.count ?? 1) > 1)',
        ),
    );

    expect($multiIndex)->toBeGreaterThanOrEqual(0);

    $page->script(athkarReaderCommandScript("data.setActiveIndex({$multiIndex});"));

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $multiIndex);

    $selector = '[data-athkar-slide][data-active="true"] .sm\\:flex button[aria-label="إتمام الذكر"]';
    waitForScript(
        $page,
        js_template(<<<'JS'
(() => {
  const button = document.querySelector({{selector}});
  const bp = window.Alpine?.store?.('bp');

  if (!button || !bp) {
    return false;
  }

  const styles = getComputedStyle(button);

  return (
    bp.isTouch?.() === true &&
    bp.is?.('sm+') === true &&
    styles.opacity === '1' &&
    styles.pointerEvents !== 'none'
  );
})()
JS,
            ['selector' => $selector],
        ),
        true,
    );

    /** @var array<string, mixed> $buttonState */
    $buttonState = $page->script(js_template(<<<'JS'
(() => {
  const button = document.querySelector({{selector}});
  const bp = window.Alpine?.store?.('bp');

  if (!button) {
    return {
      exists: false,
      width: window.innerWidth,
      isTouch: bp?.isTouch?.() ?? null,
      isSmPlus: bp?.is?.('sm+') ?? null,
    };
  }

  const styles = getComputedStyle(button);

  return {
    exists: true,
    width: window.innerWidth,
    isTouch: bp?.isTouch?.() ?? null,
    isSmPlus: bp?.is?.('sm+') ?? null,
    className: button.className,
    styleAttr: button.getAttribute('style'),
    display: styles.display,
    opacity: styles.opacity,
    pointerEvents: styles.pointerEvents,
  };
})()
JS, ['selector' => $selector]));

    expect($buttonState['exists'] ?? false)->toBeTrue('Button state: '.var_export($buttonState, true));
    expect($buttonState['opacity'] ?? null)->toBe('1', 'Button state: '.var_export($buttonState, true));
    expect($buttonState['pointerEvents'] ?? null)->not->toBe('none', 'Button state: '.var_export($buttonState, true));

    scriptClick($page, $selector);

    waitForScript($page, 'Boolean(document.querySelector(".fi-modal-window"))', true);
    clickModalAction($page, 'نعم، أكملت قراءته');

    waitForScript(
        $page,
        athkarReaderDataScript('data.countAt('.$multiIndex.') === data.requiredCount('.$multiIndex.')'),
        true,
    );
});

it('enables touch-only scrolling for overflowing athkar text', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    enableTabletContext($page);
    waitForReaderVisible($page);

    $page->script(athkarReaderCommandScript(<<<'JS'
const activeIndex = data.activeIndex;

if (!data.activeList?.[activeIndex]) {
  return;
}

data.activeList[activeIndex].text = Array.from(
  { length: 140 },
  () => 'سبحان الله والحمد لله ولا إله إلا الله والله أكبر'
).join(' ');
data.hideOrigin();
data.queueReaderTextFit();
JS));

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const bp = window.Alpine?.store?.('bp');
  const slide = document.querySelector('[data-athkar-slide][data-active="true"]');
  const box = slide?.querySelector('[data-athkar-text-box]');

  if (!box) {
    return false;
  }

  const isTouch = bp?.hasTouch ?? bp?.isTouch?.();
  const styles = getComputedStyle(box);

  if (isTouch !== true || !document.documentElement.classList.contains('has-touch')) {
    return false;
  }

  if (!box.classList.contains('athkar-text-box--touch-scroll')) {
    return false;
  }

  if (box.dataset.athkarTouchScroll !== 'true') {
    return false;
  }

  if (styles.overflowY !== 'auto') {
    return false;
  }

  if (box.scrollHeight <= box.clientHeight + 1) {
    return false;
  }

  box.scrollTop = Math.min(180, box.scrollHeight - box.clientHeight);

  return box.scrollTop > 0;
})()
JS,
        true,
    );
});

it('applies scrollability per active layer between text and origin', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    enableTabletContext($page);
    waitForReaderVisible($page);

    $page->script(athkarReaderCommandScript(<<<'JS'
const activeIndex = data.activeIndex;

if (!data.activeList?.[activeIndex]) {
  return;
}

data.activeList[activeIndex].text = 'لا إله إلا الله وحده لا شريك له له الملك وله الحمد وهو على كل شيء قدير';
data.activeList[activeIndex].origin = Array.from(
  { length: 130 },
  () => 'حدثنا عبد الله بن مسلمة عن مالك عن سمي عن أبي صالح'
).join(' ');
data.hideOrigin();
data.queueReaderTextFit();
JS));

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const box = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-text-box]');
  if (!box) {
    return false;
  }

  return (
    box.dataset.athkarTextOverflow === 'false' &&
    box.dataset.athkarOriginOverflow === 'true' &&
    box.dataset.athkarTouchScroll === 'false' &&
    !box.classList.contains('athkar-text-box--touch-scroll')
  );
})()
JS,
        true,
    );

    $page->script(athkarReaderCommandScript('data.toggleOrigin(data.activeIndex);'));

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const box = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-text-box]');
  if (!box) {
    return false;
  }

  return (
    box.dataset.athkarScrollTarget === 'origin' &&
    box.dataset.athkarTouchScroll === 'true' &&
    box.classList.contains('athkar-text-box--touch-scroll') &&
    box.classList.contains('athkar-text-box--origin-scroll') &&
    box.scrollHeight > box.clientHeight + 1
  );
})()
JS,
        true,
    );
});

it('keeps multiline wrapping and scroll detection when min and max text sizes differ', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    enableMobileContext($page);
    waitForReaderVisible($page);

    $minimumMainTextSize = Setting::MIN_MAIN_TEXT_SIZE_MIN;
    $maximumMainTextSize = min(20, Setting::MAX_MAIN_TEXT_SIZE_MAX);

    setAthkarSettings($page, [
        'minimum_main_text_size' => $minimumMainTextSize,
        'maximum_main_text_size' => $maximumMainTextSize,
    ]);
    waitForAthkarSettings($page, [
        'minimum_main_text_size' => $minimumMainTextSize,
        'maximum_main_text_size' => $maximumMainTextSize,
    ]);

    $page->script(athkarReaderCommandScript(<<<'JS'
const activeIndex = data.activeIndex;

if (!data.activeList?.[activeIndex]) {
  return;
}

data.activeList[activeIndex].text = Array.from(
  { length: 180 },
  () => 'سبحان الله والحمد لله ولا إله إلا الله والله أكبر'
).join(' ');
data.hideOrigin();
data.queueReaderTextFit();
JS));

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const slide = document.querySelector('[data-athkar-slide][data-active="true"]');
  const box = slide?.querySelector('[data-athkar-text-box]');
  const text = slide?.querySelector('[data-athkar-text]');
  if (!box || !text) {
    return false;
  }

  const whiteSpace = getComputedStyle(text).whiteSpace;

  return (
    whiteSpace !== 'nowrap' &&
    box.dataset.athkarTextOverflow === 'true' &&
    box.dataset.athkarTouchScroll === 'true' &&
    box.classList.contains('athkar-text-box--touch-scroll')
  );
})()
JS,
        true,
    );
});

it('re-arms shimmer when toggling between text and origin layers', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    enableMobileContext($page);
    waitForReaderVisible($page);
    $expectsShimmer = ! isFastBrowserMode();

    $originIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item) => String(item?.origin ?? "").trim().length > 0 || Boolean(item?.is_original))',
        ),
    );

    expect($originIndex)->toBeGreaterThanOrEqual(0);

    $page->script(athkarReaderCommandScript(js_template(<<<'JS'
data.setActiveIndex({{index}});
const activeIndex = data.activeIndex;

if (!data.activeList?.[activeIndex]) {
  return;
}

data.activeList[activeIndex].text = Array.from(
  { length: 80 },
  () => 'لا إله إلا الله وحده لا شريك له'
).join(' ');
data.activeList[activeIndex].origin = Array.from(
  { length: 80 },
  () => 'حدثنا عبد الله بن مسلمة عن مالك عن سمي عن أبي صالح'
).join(' ');
data.hideOrigin();
data.queueReaderTextFit();

requestAnimationFrame(() => {
  const slide = document.querySelector('[data-athkar-slide][data-active="true"]');
  const text = slide?.querySelector('[data-athkar-text]');
  const origin = slide?.querySelector('[data-athkar-origin-text]');

  [text, origin].forEach((node) => {
    if (!node) {
      return;
    }

    node.dataset.shimmerDelay = '20';
    node.dataset.shimmerDuration = '120';
    node.dataset.shimmerPause = '120';
  });

  data.setupTextShimmer();
});
JS, ['index' => $originIndex])));

    waitForScript(
        $page,
        js_template(<<<'JS'
(() => {
  const text = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-text]');
  const expectsShimmer = Boolean({{expectsShimmer}});

  if (!text) {
    return false;
  }

  if (!expectsShimmer) {
    return true;
  }

  return text.classList.contains('is-shimmering');
})()
JS, ['expectsShimmer' => $expectsShimmer]),
        true,
    );

    $page->script(athkarReaderCommandScript('data.toggleOrigin(data.activeIndex);'));

    waitForScript(
        $page,
        js_template(<<<'JS'
(() => {
  const slide = document.querySelector('[data-athkar-slide][data-active="true"]');
  const origin = slide?.querySelector('[data-athkar-origin-text]');
  const isVisible = slide?.querySelector('.athkar-origin-text')?.classList.contains('is-origin-visible');
  const expectsShimmer = Boolean({{expectsShimmer}});

  if (!isVisible || !origin) {
    return false;
  }

  if (!expectsShimmer) {
    return true;
  }

  return origin.classList.contains('is-shimmering');
})()
JS, ['expectsShimmer' => $expectsShimmer]),
        true,
    );

    $page->script(athkarReaderCommandScript('data.toggleOrigin(data.activeIndex);'));

    waitForScript(
        $page,
        js_template(<<<'JS'
(() => {
  const slide = document.querySelector('[data-athkar-slide][data-active="true"]');
  const text = slide?.querySelector('[data-athkar-text]');
  const isOriginVisible = slide?.querySelector('.athkar-origin-text')?.classList.contains('is-origin-visible');
  const expectsShimmer = Boolean({{expectsShimmer}});

  if (isOriginVisible || !text) {
    return false;
  }

  if (!expectsShimmer) {
    return true;
  }

  return text.classList.contains('is-shimmering');
})()
JS, ['expectsShimmer' => $expectsShimmer]),
        true,
    );
});

it('keeps text scrollable after toggling origin on and back off', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);
    enableTabletContext($page);
    waitForReaderVisible($page);

    $page->script(athkarReaderCommandScript(<<<'JS'
const activeIndex = data.activeIndex;

if (!data.activeList?.[activeIndex]) {
  return;
}

data.activeList[activeIndex].text = Array.from(
  { length: 140 },
  () => 'سبحان الله والحمد لله ولا إله إلا الله والله أكبر'
).join(' ');
data.activeList[activeIndex].origin = Array.from(
  { length: 120 },
  () => 'حدثنا عبد الله بن مسلمة عن مالك عن سمي عن أبي صالح عن أبي هريرة رضي الله عنه'
).join(' ');
data.hideOrigin();
data.queueReaderTextFit();
JS));

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const box = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-text-box]');
  if (!box) {
    return false;
  }

  return box.dataset.athkarTextOverflow === 'true' && box.dataset.athkarTouchScroll === 'true';
})()
JS,
        true,
    );

    $page->script(athkarReaderCommandScript('data.toggleOrigin(data.activeIndex);'));

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const box = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-text-box]');
  if (!box) {
    return false;
  }

  return (
    box.dataset.athkarScrollTarget === 'origin' &&
    box.dataset.athkarOriginOverflow === 'true' &&
    box.classList.contains('athkar-text-box--origin-scroll')
  );
})()
JS,
        true,
    );

    $page->script(athkarReaderCommandScript('data.toggleOrigin(data.activeIndex);'));

    waitForScript(
        $page,
        <<<'JS'
(() => {
  const box = document.querySelector('[data-athkar-slide][data-active="true"] [data-athkar-text-box]');
  if (!box) {
    return false;
  }

  if (box.dataset.athkarScrollTarget !== 'text') {
    return false;
  }

  if (box.dataset.athkarTextOverflow !== 'true' || box.dataset.athkarTouchScroll !== 'true') {
    return false;
  }

  if (box.classList.contains('athkar-text-box--origin-scroll')) {
    return false;
  }

  box.scrollTop = Math.min(160, box.scrollHeight - box.clientHeight);

  return box.scrollTop > 0;
})()
JS,
        true,
    );
});

it('tracks progress by letters and counters by counts while updating page position', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    $settings = [
        'does_automatically_switch_completed_athkar' => false,
        'does_prevent_switching_athkar_until_completion' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    $singleIndex = $page->script(
        athkarReaderDataScript(
            'data.activeList.findIndex((item, index) => Number(item.count ?? 1) === 1 && index < data.activeList.length - 1)',
        ),
    );

    expect($singleIndex)->toBeGreaterThanOrEqual(0);

    $page->script(athkarReaderCommandScript("data.setActiveIndex({$singleIndex});"));

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex);

    $initialLetters = $page->script(athkarReaderDataScript('data.totalCompletedLetters'));
    $initialCounts = $page->script(athkarReaderDataScript('data.totalCompletedCount'));

    scriptClick($page, '[data-athkar-slide][data-active="true"] [data-athkar-tap]');

    waitForScript($page, athkarReaderDataScript('data.countAt(data.activeIndex)'), 1);

    $completedLetters = $page->script(athkarReaderDataScript('data.totalCompletedLetters'));
    $completedCounts = $page->script(athkarReaderDataScript('data.totalCompletedCount'));
    $completedPercent = $page->script(athkarReaderDataScript('data.slideProgressPercent'));

    expect($completedLetters)->toBeGreaterThan($initialLetters);
    expect($completedCounts)->toBe($initialCounts + 1);

    scriptClick($page, '[data-athkar-slide][data-active="true"] [data-athkar-tap]');

    waitForScript($page, athkarReaderDataScript('data.countAt(data.activeIndex)'), 2);

    $overcountLetters = $page->script(athkarReaderDataScript('data.totalCompletedLetters'));
    $overcountCounts = $page->script(athkarReaderDataScript('data.totalCompletedCount'));
    $overcountPercent = $page->script(athkarReaderDataScript('data.slideProgressPercent'));

    expect($overcountLetters)->toBe($completedLetters);
    expect($overcountCounts)->toBe($completedCounts + 1);
    expect($overcountPercent)->toBe($completedPercent);

    scriptClick($page, 'button[aria-label="التالي"]');

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $singleIndex + 1);

    $pageCount = $page->script(athkarReaderDataScript('data.activeIndex + 1'));
    $totalPages = $page->script(athkarReaderDataScript('data.activeList.length'));

    expect($pageCount)->toBe($singleIndex + 2);
    expect($totalPages)->toBeGreaterThanOrEqual($pageCount);
});

it('exposes all athkar for the active mode and navigates when switching is allowed', function () {
    $expectedCount = Thikr::query()
        ->whereIn('time', [ThikrTime::Shared, ThikrTime::Sabah])
        ->count();

    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    setAthkarSettings($page, [
        'does_prevent_switching_athkar_until_completion' => true,
    ]);

    $activeCount = $page->script(athkarReaderDataScript('data.activeList.length'));

    expect($activeCount)->toBe($expectedCount);

    waitForScript($page, athkarReaderDataScript('data.maxNavigableIndex'), 0);

    $settings = [
        'does_prevent_switching_athkar_until_completion' => false,
    ];
    setAthkarSettings($page, $settings);
    waitForAthkarSettings($page, $settings);

    $page->script(athkarReaderCommandScript('data.setActiveIndex(data.activeList.length - 1);'));

    $lastIndex = $page->script(athkarReaderDataScript('data.activeList.length - 1'));

    waitForScript($page, athkarReaderDataScript('data.activeIndex'), $lastIndex);
});

it('shows the congrats panel briefly then returns to the gate when setting 4 is disabled', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    setAthkarSettings($page, [
        'does_skip_notice_panels' => false,
    ]);

    $page->script(athkarReaderCommandScript('data.markAllActiveModeComplete();'));

    waitForScript($page, athkarReaderDataScript('data.isCompletionVisible'), true);
    waitForScriptWithTimeout($page, athkarReaderDataScript('data.isCompletionVisible'), false, 4000);
    waitForScript($page, homeDataScript('data.activeView'), 'athkar-app-gate');
    waitForScript(
        $page,
        'window.location.hash === "#athkar-app-gate" || window.location.hash === ""',
        true,
    );
});

it('resets athkar progress when the day changes', function () {
    $page = visit('/');

    resetBrowserState($page);
    openAthkarReader($page, 'sabah', false);

    scriptClick($page, '[data-athkar-slide][data-active="true"] [data-athkar-tap]');

    waitForScript($page, athkarReaderDataScript('data.totalCompletedCount'), 1);

    $page->script(
        athkarReaderCommandScript('data.lastSeenDay = "2000-01-01"; data.syncDay();'),
    );

    waitForScript($page, athkarReaderDataScript('data.activeMode'), null);
    waitForScript($page, athkarReaderDataScript('Array.isArray(data.activeList)'), true);
    waitForScript($page, athkarReaderDataScript('data.totalRequiredCount'), 0);
    waitForScript(
        $page,
        athkarReaderDataScript('data.progress.sabah.counts.every((count) => Number(count) === 0)'),
        true,
    );
});
