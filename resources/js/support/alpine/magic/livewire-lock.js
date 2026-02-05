document.addEventListener('alpine:init', () => {
    const throttle = (delay, callback, options = {}) => {
        const { noTrailing = false, noLeading = false, debounceMode = null } = options;
        let timeoutId = null;
        let cancelled = false;
        let lastExec = 0;

        const clearExistingTimeout = () => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        };

        const cancel = (opts = {}) => {
            const { upcomingOnly = false } = opts;
            clearExistingTimeout();
            cancelled = !upcomingOnly;
        };

        const wrapper = function (...args) {
            const elapsed = Date.now() - lastExec;
            const context = this;

            if (cancelled) {
                return;
            }

            const exec = () => {
                lastExec = Date.now();
                callback.apply(context, args);
            };

            const clear = () => {
                timeoutId = null;
            };

            if (!noLeading && debounceMode && !timeoutId) {
                exec();
            }

            clearExistingTimeout();

            if (debounceMode === null && elapsed > delay) {
                if (noLeading) {
                    lastExec = Date.now();
                    if (!noTrailing) {
                        timeoutId = setTimeout(debounceMode ? clear : exec, delay);
                    }
                } else {
                    exec();
                }
            } else if (!noTrailing) {
                timeoutId = setTimeout(
                    debounceMode ? clear : exec,
                    debounceMode === null ? delay - elapsed : delay,
                );
            }
        };

        wrapper.cancel = cancel;

        return wrapper;
    };

    const debounce = (delay, callback, options = {}) => {
        const { atBegin = false } = options;
        return throttle(delay, callback, { debounceMode: atBegin !== false });
    };

    window.Alpine.magic('livewireLock', (el) => {
        return (wire, delayMs = 350, enforceDelay = false) => {
            const state = window.Alpine.reactive({ locked: false });
            let stop = null;
            let pendingRequests = 0;
            let cooldownReady = true;
            let shouldEnforceDelay = true;

            const normalizeDelay = (value) => {
                const parsed = Number(value);

                if (!Number.isFinite(parsed) || parsed < 0) {
                    return 350;
                }

                return parsed;
            };

            const normalizeEnforceDelay = (value) => {
                if (typeof value === 'boolean') {
                    return value;
                }

                return true;
            };

            let cooldownDelay = normalizeDelay(delayMs);
            shouldEnforceDelay = normalizeEnforceDelay(enforceDelay);
            let endCooldown = debounce(cooldownDelay, () => {
                cooldownReady = true;
                releaseIfReady();
            });

            const setCooldownDelay = (value) => {
                const nextDelay = normalizeDelay(value);

                if (nextDelay === cooldownDelay) {
                    return;
                }

                cooldownDelay = nextDelay;
                endCooldown = debounce(cooldownDelay, () => {
                    cooldownReady = true;
                    releaseIfReady();
                });
            };

            const releaseIfReady = () => {
                if (pendingRequests === 0 && cooldownReady) {
                    state.locked = false;
                }
            };

            const startCooldown = (delayOverride, enforceOverride) => {
                shouldEnforceDelay = normalizeEnforceDelay(enforceOverride ?? shouldEnforceDelay);

                if (!shouldEnforceDelay) {
                    cooldownReady = true;
                    return;
                }

                setCooldownDelay(delayOverride ?? cooldownDelay);
                cooldownReady = false;
                endCooldown();
            };

            const getWire = () => {
                const root = el.closest('[wire\\:id]');

                if (!root || !window.Livewire?.find) {
                    return null;
                }

                return window.Livewire.find(root.getAttribute('wire:id'));
            };

            const attachInterceptor = (wireInstance) => {
                if (!wireInstance?.intercept) {
                    return;
                }

                stop = wireInstance.intercept(
                    ({ onSend, onFinish, onCancel, onError, onFailure }) => {
                        const onRequestDone = () => {
                            pendingRequests = Math.max(0, pendingRequests - 1);
                            releaseIfReady();
                        };

                        onSend(() => {
                            pendingRequests += 1;
                            state.locked = true;
                        });
                        onFinish(onRequestDone);
                        onCancel(onRequestDone);
                        onError(onRequestDone);
                        onFailure(onRequestDone);
                    },
                );
            };

            state.run = (callback, delayOverride, enforceOverride) => {
                if (state.locked) {
                    return;
                }

                state.locked = true;
                startCooldown(delayOverride, enforceOverride);

                if (typeof callback === 'function') {
                    callback();
                }

                releaseIfReady();
            };

            attachInterceptor(wire ?? getWire());

            state.bind = (wireInstance) => {
                if (stop) {
                    stop();
                    stop = null;
                }

                attachInterceptor(wireInstance);
            };

            return state;
        };
    });
});
