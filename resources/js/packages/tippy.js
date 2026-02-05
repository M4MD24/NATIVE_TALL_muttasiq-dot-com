import Tippy, { hideAll } from 'tippy.js';

const tippyInstances = new WeakMap();

document.addEventListener('alpine:init', () => {
    window.Alpine.magic('tippy', (el) => (message, direction = 'top', durationInMs = 2000) => {
        const existing = tippyInstances.get(el);
        let instance = existing;

        if (!instance || instance.state.isDestroyed) {
            instance = Tippy(el, {
                content: message,
                placement: direction,
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
                placement: direction,
                theme: window.Alpine.store('colorScheme').isDark ? 'light' : '',
            });
        }

        instance._clearHideTimer?.();

        if (!instance.state.isShown) {
            instance.show();
        }

        if (Number.isFinite(durationInMs) && durationInMs > 0) {
            instance._hideTimer = setTimeout(() => instance.hide(), durationInMs);
        }

        return instance;
    });

    window.hideAllTippies = hideAll;
});
