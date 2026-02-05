document.addEventListener('alpine:init', () => {
    window.Alpine.directive('image-loaded', (el, { expression }, { evaluateLater }) => {
        if (!(el instanceof HTMLImageElement)) {
            console.warn('x-image-loaded should be used on <img> elements');
            return;
        }

        const callback = expression ? evaluateLater(expression) : null;

        const waitForImage = async () => {
            // ? Wait for actual load
            if (!el.complete) {
                await new Promise((resolve, reject) => {
                    el.addEventListener('load', resolve, { once: true });
                    el.addEventListener('error', reject, { once: true });
                });
            }

            // ? Try decode, but NEVER fail because of it
            if (el.decode) {
                try {
                    await el.decode();
                } catch (_) {
                    // ? Ignore decode errors — image is still usable
                }
            }

            // ? Fire event + expression
            el.dispatchEvent(new CustomEvent('image-loaded', { bubbles: true }));

            if (callback) callback();
        };

        waitForImage();
    });
});
