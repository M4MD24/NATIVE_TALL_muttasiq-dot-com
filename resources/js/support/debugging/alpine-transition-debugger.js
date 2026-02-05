(() => {
    const ENABLED_FLAG = '__ALPINE_TRANSITION_DEBUG__';

    const whenAlpineReady = (cb) => {
        if (window.Alpine && window.Alpine.version) {
            cb(window.Alpine);
        } else {
            document.addEventListener('alpine:init', () => cb(window.Alpine), {
                once: true,
            });
        }
    };

    const captureStack = () => {
        const stack = new Error().stack;
        if (!stack) return 'stack unavailable';
        return stack
            .split('\n')
            .slice(2, 8)
            .map((line) => line.trim())
            .join('\n');
    };

    const summariseEl = (el) => {
        if (!el) return '<missing element>';
        const attrs = Array.from(el.attributes || [])
            .map((attr) => `${attr.name}="${attr.value}"`)
            .join(' ');
        const tag = el.tagName ? el.tagName.toLowerCase() : 'unknown';
        return `<${tag}${attrs ? ' ' + attrs : ''}>`;
    };

    const install = () => {
        window.addEventListener('unhandledrejection', (event) => {
            const reason = event.reason;
            if (reason && reason.isFromCancelledTransition) {
                console.groupCollapsed('[Alpine transition] cancelled');
                console.debug('reason', reason);
                console.debug('stack', captureStack());
                console.groupEnd();
                event.preventDefault();
            }
        });

        const proto = window.Element && window.Element.prototype;
        if (!proto || proto.__x_transition_debug_patched) return;
        const original = proto._x_toggleAndCascadeWithTransitions;
        if (!original) return;

        proto._x_toggleAndCascadeWithTransitions = function (_el, _value, _show, _hide) {
            try {
                // console.groupCollapsed('[Alpine transition] toggle');
                // console.debug('value', value);
                // console.debug('target', summariseEl(el));
                // console.debug('stack', captureStack());
                // console.groupEnd();
            } catch (error) {
                console.warn('Alpine transition debug failed', error);
            }

            return original.apply(this, arguments);
        };

        proto.__x_transition_debug_patched = true;
    };

    whenAlpineReady(install);

    const TRANSITION_ATTRS = [
        'x-transition',
        'x-transition:enter',
        'x-transition:enter-start',
        'x-transition:enter-end',
        'x-transition:leave',
        'x-transition:leave-start',
        'x-transition:leave-end',
        'x-transition:opacity',
        'x-transition:scale',
    ];

    // Escape each attribute for use in a CSS selector
    const SELECTOR = TRANSITION_ATTRS.map((attr) => `[${CSS.escape(attr)}]`).join(',');

    const stamp = (el) => {
        if (!el || el.__x_transition_logged) return;

        const labels = new Set();

        const log = (type, event) => {
            if (!window[ENABLED_FLAG]) return;
            const key = `${type}-${event?.target?.__x_transition_uid ?? ''}`;
            if (labels.has(key)) return;
            labels.add(key);
            console.groupCollapsed(`[x-transition:${type}]`, summariseEl(el));
            console.debug('event', type, {
                duration: event?.elapsedTime,
                propertyName: event?.propertyName,
                pseudoElement: event?.pseudoElement,
            });
            console.debug('stack', captureStack());
            console.groupEnd();
        };

        const bind = (type) => el.addEventListener(type, (event) => log(type, event));

        bind('transitionrun');
        bind('transitionstart');
        bind('transitionend');
        bind('transitioncancel');
        bind('animationstart');
        bind('animationend');
        bind('animationcancel');

        el.__x_transition_uid = Date.now().toString(36) + Math.random().toString(36).slice(2);
        el.__x_transition_logged = true;
    };

    const scan = (root = document) => {
        root.querySelectorAll(SELECTOR).forEach((el) => stamp(el));
    };

    whenAlpineReady(() => scan());

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) return;
                stamp(node);
                scan(node);
            });
        }
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });
})();
