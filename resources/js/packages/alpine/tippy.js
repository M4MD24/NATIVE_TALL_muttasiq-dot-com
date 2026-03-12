import Tippy, { hideAll } from 'tippy.js';

const tippyInstances = new WeakMap();
const tooltipSuppressionUntil = {
    timestamp: 0,
};
const readerRootSelector = '[data-athkar-app-reader-root]';

const suppressTooltipsFor = (durationInMs = 400) => {
    const duration = Number.isFinite(durationInMs) ? Math.max(0, durationInMs) : 400;
    tooltipSuppressionUntil.timestamp = Date.now() + duration;
};

const areTooltipsSuppressed = () => tooltipSuppressionUntil.timestamp > Date.now();

const normalizeTooltipOptions = (direction = 'top', durationInMs = 2000, options = {}) => {
    if (direction && typeof direction === 'object' && !Array.isArray(direction)) {
        return {
            placement: direction.placement ?? direction.direction ?? 'top',
            durationInMs: direction.durationInMs ?? direction.duration ?? 2000,
            showWhenGuidancePanelsSkipped: Boolean(direction.showWhenGuidancePanelsSkipped),
        };
    }

    const normalizedOptions =
        options && typeof options === 'object' && !Array.isArray(options) ? options : {};

    return {
        placement: direction,
        durationInMs,
        showWhenGuidancePanelsSkipped: Boolean(normalizedOptions.showWhenGuidancePanelsSkipped),
    };
};

const hideTooltipInstance = (instance) => {
    if (!instance) {
        return null;
    }

    instance._clearHideTimer?.();
    instance.hide();

    return null;
};

const resolveReaderState = (el) => {
    const readerRoot = el.closest(readerRootSelector);

    if (!readerRoot || !window.Alpine?.$data) {
        return null;
    }

    return window.Alpine.$data(readerRoot);
};

const areGuidancePanelsSkipped = (el) => {
    const readerState = resolveReaderState(el);

    if (!readerState || typeof readerState.shouldSkipGuidancePanels !== 'function') {
        return false;
    }

    return Boolean(readerState.shouldSkipGuidancePanels());
};

const hideAllTooltips = ({ duration = 0, suppressMs = 0 } = {}) => {
    hideAll({ duration });

    if (suppressMs > 0) {
        suppressTooltipsFor(suppressMs);
    }
};

document.addEventListener('alpine:init', () => {
    window.Alpine.magic('tippy', (el) => {
        const showTooltip = (message, direction = 'top', durationInMs = 2000, options = {}) => {
            const {
                placement,
                durationInMs: resolvedDuration,
                showWhenGuidancePanelsSkipped,
            } = normalizeTooltipOptions(direction, durationInMs, options);

            const existing = tippyInstances.get(el);
            let instance = existing;

            if (!instance || instance.state.isDestroyed) {
                instance = Tippy(el, {
                    content: message,
                    placement,
                    trigger: 'manual',
                    theme: window.Alpine.store('colorScheme').isDark ? 'light' : '',
                });

                instance._hideTimer = null;
                instance._clearHideTimer = () => {
                    if (instance._hideTimer) {
                        clearTimeout(instance._hideTimer);
                        instance._hideTimer = null;
                    }
                };

                tippyInstances.set(el, instance);
            } else {
                instance.setContent(message);
                instance.setProps({
                    placement,
                    theme: window.Alpine.store('colorScheme').isDark ? 'light' : '',
                });
            }

            instance._clearHideTimer?.();

            if (
                areTooltipsSuppressed() ||
                (!showWhenGuidancePanelsSkipped && areGuidancePanelsSkipped(el))
            ) {
                instance.hide();

                return instance;
            }

            if (!instance.state.isShown) {
                instance.show();
            }

            if (Number.isFinite(resolvedDuration) && resolvedDuration > 0) {
                instance._hideTimer = setTimeout(() => instance.hide(), resolvedDuration);
            }

            return instance;
        };

        showTooltip.hide = () => hideTooltipInstance(tippyInstances.get(el));

        return showTooltip;
    });

    const modalLifecycleEvents = [
        'open-modal',
        'x-modal-opened',
        'close-modal',
        'close-modal-quietly',
        'modal-closed',
        'x-modal-closed',
    ];

    modalLifecycleEvents.forEach((eventName) => {
        window.addEventListener(eventName, () => {
            hideAllTooltips({ duration: 0, suppressMs: 450 });
        });
    });

    window.hideAllTippies = hideAllTooltips;
});
