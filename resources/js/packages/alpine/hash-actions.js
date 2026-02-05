document.addEventListener('alpine:init', () => {
    const normalizeHash = (value) => {
        if (typeof value !== 'string' || value.length === 0) {
            return '#';
        }

        return value.startsWith('#') ? value : `#${value}`;
    };

    const buildUrl = (hash) => {
        const baseUrl = `${window.location.pathname}${window.location.search}`;
        return hash === '#' ? baseUrl : `${baseUrl}${hash}`;
    };

    const dispatchHashChange = (oldUrl, newUrl) => {
        let event;

        try {
            event = new HashChangeEvent('hashchange', {
                oldURL: oldUrl,
                newURL: newUrl,
            });
        } catch (_) {
            event = new Event('hashchange');
        }

        window.dispatchEvent(event);
    };

    const applyHash = (
        nextHash,
        { rememberInHistory = false, rememberInState = rememberInHistory } = {},
    ) => {
        const previousHash = window.location.hash || '#';
        const normalizedHash = normalizeHash(nextHash);
        const oldUrl = window.location.href;
        const nextState = {
            ...(window.history.state ?? {}),
            __hashActionRemember: rememberInState && normalizedHash !== '#',
            __hashActionPrev: previousHash,
        };
        const newUrl = buildUrl(normalizedHash);

        if (oldUrl === newUrl) {
            window.history.replaceState(nextState, document.title, newUrl);
            return;
        }

        if (rememberInHistory && normalizedHash !== '#') {
            window.history.pushState(nextState, document.title, newUrl);
        } else {
            window.history.replaceState(nextState, document.title, newUrl);
        }

        if (oldUrl !== window.location.href) {
            dispatchHashChange(oldUrl, window.location.href);
        }
    };

    const resolveAction = (actions, hash) => {
        if (!actions || typeof actions !== 'object') {
            return null;
        }

        return actions[hash] ?? actions[hash.slice(1)] ?? null;
    };

    const dispatchSwitchView = (view, { restoring = false } = {}) => {
        if (!view) {
            return;
        }

        window.dispatchEvent(
            new CustomEvent('switch-view', {
                detail: { to: view, restoring },
            }),
        );
    };

    const runNativeBack = (targetHash) => {
        window.__hashActionBypassLock = true;
        applyHash(targetHash, { rememberInHistory: false, rememberInState: true });
        setTimeout(() => {
            window.__hashActionBypassLock = false;
        }, 0);
    };

    window.__nativeBackAction = () => {
        const currentHash = normalizeHash(window.location.hash || '#');

        if (currentHash === '#athkar-app-gate') {
            runNativeBack('#main-menu');
            return true;
        }

        if (currentHash === '#athkar-app-sabah' || currentHash === '#athkar-app-masaa') {
            runNativeBack('#athkar-app-gate');
            return true;
        }

        return false;
    };

    window.Alpine.magic('hashAction', () => {
        return (hash, options = {}) => {
            const { reset = '#', force = true, remember = false } = options;
            const targetHash = normalizeHash(hash);

            if (targetHash === '#') {
                return;
            }

            const resetHash = normalizeHash(reset);
            const currentHash = window.location.hash || '#';

            if (force && currentHash === targetHash) {
                applyHash(resetHash, { rememberInHistory: false, rememberInState: false });
                setTimeout(() => {
                    applyHash(targetHash, {
                        rememberInHistory: remember,
                        rememberInState: remember,
                    });
                }, 0);
                return;
            }

            applyHash(targetHash, { rememberInHistory: remember, rememberInState: remember });
        };
    });

    window.Alpine.directive('hash-actions', (el, { expression }, { evaluateLater, cleanup }) => {
        const evaluateActions = evaluateLater(expression);

        const handleHash = (hash) => {
            const normalizedHash = normalizeHash(hash);

            if (normalizedHash === '#') {
                return;
            }

            evaluateActions((actions) => {
                const action = resolveAction(actions, normalizedHash);

                if (typeof action !== 'function') {
                    return;
                }

                const shouldRemember = window.history.state?.__hashActionRemember === true;

                if (!shouldRemember) {
                    window.history.replaceState(
                        {
                            ...(window.history.state ?? {}),
                            __hashActionRemember: false,
                        },
                        document.title,
                        window.location.href,
                    );
                }

                action();

                setTimeout(() => {
                    if (shouldRemember) {
                        return;
                    }

                    if (normalizeHash(window.location.hash || '#') === normalizedHash) {
                        applyHash('#', { rememberInHistory: false, rememberInState: false });
                    }
                }, 0);
            });
        };

        const onHashChange = () => {
            handleHash(window.location.hash || '#');
        };

        window.addEventListener('hashchange', onHashChange);
        setTimeout(() => {
            const defaultHash = normalizeHash(el.dataset?.hashDefault);

            if (defaultHash !== '#') {
                dispatchSwitchView(defaultHash.slice(1), { restoring: true });
                applyHash(defaultHash, {
                    rememberInHistory: false,
                    rememberInState: true,
                });
                return;
            }

            onHashChange();
        }, 0);

        cleanup(() => {
            window.removeEventListener('hashchange', onHashChange);
        });
    });
});
