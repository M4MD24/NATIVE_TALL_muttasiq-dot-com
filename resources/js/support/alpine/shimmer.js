const defaultDurationMs = 1000;
const defaultDelayMs = 5000;
const defaultPauseMs = 10000;

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

export const createShimmerController = ({
    resolveRoot,
    resolveUseAlternateTarget,
    selectors = {},
    classes = {},
} = {}) => {
    const activeContainerSelector =
        selectors.activeContainer ?? '[data-shimmer-slide][data-active="true"]';
    const primaryTargetSelector = selectors.primaryTarget ?? '[data-shimmer-text]';
    const alternateTargetSelector = selectors.alternateTarget ?? '[data-shimmer-alt-text]';
    const shimmerTargetSelector = selectors.shimmerTarget ?? '[data-shimmer-target]';
    const mutedClass = classes.muted ?? 'is-muted';
    const shimmerClass = classes.shimmer ?? 'shimmer';
    const shimmeringClass = classes.shimmering ?? 'is-shimmering';

    const state = {
        target: null,
        timer: null,
        runTimer: null,
        duration: defaultDurationMs,
        delay: defaultDelayMs,
        pause: defaultPauseMs,
        generation: 0,
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
        state.generation += 1;
        clearTimers();

        if (state.target) {
            state.target.classList.remove(shimmeringClass);
        }

        const root = resolveRoot?.();
        root?.querySelectorAll(`${shimmerTargetSelector}.${shimmeringClass}`)?.forEach((node) => {
            node.classList.remove(shimmeringClass);
        });

        state.target = null;
    };

    const schedule = ({ immediate = false } = {}) => {
        const generation = state.generation + 1;
        state.generation = generation;
        clearTimers();

        const { target, delay, duration, pause } = state;

        if (!target) {
            return;
        }

        const run = () => {
            if (
                state.generation !== generation ||
                !state.target ||
                state.target !== target ||
                !document.contains(target)
            ) {
                return;
            }

            target.classList.add(shimmeringClass);
            state.runTimer = setTimeout(() => {
                if (
                    state.generation !== generation ||
                    state.target !== target ||
                    !document.contains(target)
                ) {
                    return;
                }

                target.classList.remove(shimmeringClass);
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
        const activeContainer = root?.querySelector(activeContainerSelector);
        const useAlternateTarget = Boolean(resolveUseAlternateTarget?.());
        const target =
            text ??
            activeContainer?.querySelector(
                useAlternateTarget ? alternateTargetSelector : primaryTargetSelector,
            );

        if (!target || target.classList.contains(mutedClass)) {
            stop();
            return;
        }

        activeContainer
            ?.querySelectorAll(`${shimmerTargetSelector}.${shimmeringClass}`)
            ?.forEach((node) => {
                if (node !== target) {
                    node.classList.remove(shimmeringClass);
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
        target.classList.add(shimmerClass);

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
