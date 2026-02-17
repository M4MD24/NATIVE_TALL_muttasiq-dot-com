const defaultDurationMs = 1000;
const defaultDelayMs = 1000;
const defaultPauseMs = 4000;

const parseTime = (value, fallback) => {
    if (value === null || value === undefined) {
        return fallback;
    }

    const text = String(value).trim();
    const parsed = Number.parseFloat(text);

    if (!Number.isFinite(parsed)) {
        return fallback;
    }

    if (text.endsWith('ms')) {
        return parsed;
    }

    if (text.endsWith('s')) {
        return parsed * 1000;
    }

    return parsed;
};

export const createAthkarShimmerController = ({
    resolveRoot,
    resolveIsOriginVisible,
} = {}) => {
    const state = {
        target: null,
        timer: null,
        runTimer: null,
        duration: defaultDurationMs,
        delay: defaultDelayMs,
        pause: defaultPauseMs,
    };

    const clearTimers = () => {
        if (state.timer) {
            clearTimeout(state.timer);
            state.timer = null;
        }

        if (state.runTimer) {
            clearTimeout(state.runTimer);
            state.runTimer = null;
        }
    };

    const stop = () => {
        clearTimers();

        if (state.target) {
            state.target.classList.remove('is-shimmering');
        }

        const root = resolveRoot?.();
        root?.querySelectorAll('[data-athkar-shimmer].is-shimmering')?.forEach((node) => {
            node.classList.remove('is-shimmering');
        });

        state.target = null;
    };

    const schedule = ({ immediate = false } = {}) => {
        clearTimers();

        const { target, delay, duration, pause } = state;

        if (!target) {
            return;
        }

        const run = () => {
            if (!state.target || state.target !== target) {
                return;
            }

            target.classList.add('is-shimmering');
            state.runTimer = setTimeout(() => {
                if (state.target !== target) {
                    return;
                }

                target.classList.remove('is-shimmering');
                state.timer = setTimeout(run, pause);
            }, duration);
        };

        if (immediate) {
            run();
            return;
        }

        state.timer = setTimeout(run, delay);
    };

    const setup = (text = null, { immediate = false } = {}) => {
        const root = resolveRoot?.();
        const activeSlide = root?.querySelector('[data-athkar-slide][data-active="true"]');
        const isOriginVisible = Boolean(resolveIsOriginVisible?.());
        const target =
            text ??
            activeSlide?.querySelector(
                isOriginVisible ? '[data-athkar-origin-text]' : '[data-athkar-text]',
            );

        if (!target || target.classList.contains('athkar-text--muted')) {
            stop();
            return;
        }

        activeSlide?.querySelectorAll('[data-athkar-shimmer].is-shimmering')?.forEach((node) => {
            if (node !== target) {
                node.classList.remove('is-shimmering');
            }
        });

        if (state.target === target) {
            if (immediate) {
                schedule({ immediate: true });
                return;
            }

            if (!state.timer && !state.runTimer) {
                schedule();
            }

            return;
        }

        stop();
        state.target = target;
        target.classList.add('athkar-shimmer');

        const duration = parseTime(target.dataset.shimmerDuration, defaultDurationMs);
        const delay = parseTime(target.dataset.shimmerDelay, defaultDelayMs);
        const pause = parseTime(target.dataset.shimmerPause, defaultPauseMs);

        state.duration = Number.isFinite(duration) ? duration : defaultDurationMs;
        state.delay = Number.isFinite(delay) ? delay : defaultDelayMs;
        state.pause = Number.isFinite(pause) ? pause : defaultPauseMs;

        target.style.setProperty('--shimmer-duration', `${state.duration}ms`);
        schedule({ immediate });
    };

    return {
        setup,
        stop,
        destroy: stop,
    };
};
