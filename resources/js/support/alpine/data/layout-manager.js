document.addEventListener('alpine:init', () => {
    window.Alpine.data('layoutManager', () => ({
        isFontReady: false,
        isLayoutSetUp: false,
        isBodyVisible: false,
        isBlinkerShown: true,
        defaultTransitionDurationInMs: 750,
        fastTransitionDurationInMs: 250,
        useFastTransitionDuration: false,
        isActionOpen: false,
        isScrollingDisabled: false,

        init() {
            // ? Keep track of Filament action events
            window.addEventListener('open-modal', () => (this.isActionOpen = true));
            window.addEventListener('close-modal-quietly', () => (this.isActionOpen = false));

            // // ? Auto-scroll to the top instantly upon load
            // if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
            // window.addEventListener('beforeunload', () => window.Alpine.$topScroll());
            // window.addEventListener('load', () => window.Alpine.$topScroll());

            // ? Wait for font loading
            this.$store.fontManager.ready(() => (this.isFontReady = true));

            // ? Mark layout ready when both have finished
            window.Alpine.effect(() => {
                this.isLayoutSetUp = this.isFontReady;

                if (this.isLayoutSetUp) {
                    this.isBlinkerShown = false;
                    this.isBodyVisible = true;
                }
            });
        },

        blink(
            shouldAwaitLivewire = false,
            shouldKeepWaiting = false,
            useDefaultTransitionDuration = false,
        ) {
            this.useFastTransitionDuration = true;
            this.isBlinkerShown = true;
            this.isBodyVisible = false;

            if (shouldKeepWaiting) {
                // never resolves -> any .then() never runs
                return new Promise(() => {});
            }

            const showBody = () => {
                this.useFastTransitionDuration = false;
                this.isBodyVisible = true;
                this.isBlinkerShown = false;
            };

            const duration = useDefaultTransitionDuration
                ? this.defaultTransitionDurationInMs
                : this.fastTransitionDurationInMs;

            return new Promise((resolve) => {
                const done = () => {
                    showBody();
                    resolve();
                };

                if (shouldAwaitLivewire) {
                    const stop = this.$wire.$hook('morphed', () => {
                        done();
                        stop();
                    });
                } else {
                    setTimeout(done, duration);
                }
            });
        },
    }));
});
