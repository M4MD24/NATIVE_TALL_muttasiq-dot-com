@if (config('app.browser_test_fast_mode'))
    <style>
        body.test-fast-ui *,
        body.test-fast-ui *::before,
        body.test-fast-ui *::after {
            transition-duration: 0ms !important;
            transition-delay: 0ms !important;
            animation-duration: 0ms !important;
            animation-delay: 0ms !important;
        }
    </style>
    <script>
        (() => {
            window.__APP_BROWSER_TEST_FAST_UI = true;

            const clampDelay = (delay) => {
                const normalizedDelay = Number(delay ?? 0);

                if (!Number.isFinite(normalizedDelay)) {
                    return 0;
                }

                if (normalizedDelay <= 0) {
                    return 0;
                }

                if (normalizedDelay <= 500) {
                    return normalizedDelay;
                }

                const scaledDelay = Math.ceil(normalizedDelay * 0.12);

                return Math.min(Math.max(160, scaledDelay), 500);
            };

            const nativeSetTimeout = window.setTimeout.bind(window);
            const nativeSetInterval = window.setInterval.bind(window);

            window.setTimeout = (handler, delay, ...args) => {
                return nativeSetTimeout(handler, clampDelay(delay), ...args);
            };

            window.setInterval = (handler, delay, ...args) => {
                return nativeSetInterval(handler, clampDelay(delay), ...args);
            };
        })();
    </script>
@endif
