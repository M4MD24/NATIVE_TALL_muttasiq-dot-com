document.addEventListener('alpine:init', () => {
    window.Alpine.store('fontManager', {
        arabicFontSans: null,
        arabicFontSerif: null,
        englishFontSans: null,
        warmupPromise: null,

        _getPrimaryFamily(fontStack) {
            if (!fontStack) {
                return null;
            }

            const primary = fontStack.split(',')[0]?.trim();

            return primary || null;
        },

        _resolveFamilies() {
            const root = getComputedStyle(document.documentElement);
            this.arabicFontSans = root.getPropertyValue('--font-arabic-sans')?.trim() || null;
            this.arabicFontSerif = root.getPropertyValue('--font-arabic-serif')?.trim() || null;
            this.englishFontSans = root.getPropertyValue('--font-english-sans')?.trim() || null;
        },

        _primeFonts() {
            if (!('fonts' in document) || !document.fonts?.load) {
                return Promise.resolve();
            }

            if (this.warmupPromise) {
                return this.warmupPromise;
            }

            const families = [
                this._getPrimaryFamily(this.arabicFontSerif),
                this._getPrimaryFamily(this.arabicFontSans),
                this._getPrimaryFamily(this.englishFontSans),
            ]
                .filter(Boolean)
                .filter((value, index, list) => list.indexOf(value) === index);

            this.warmupPromise = Promise.allSettled(
                families.map((family) => document.fonts.load(`1em ${family}`)),
            );

            return this.warmupPromise;
        },

        ready(callback) {
            this._resolveFamilies();
            const warmupPromise = this._primeFonts();

            // ? Using Font Loading API when available
            if ('fonts' in document && document.fonts?.ready) {
                let isReady = false;
                const done = () => {
                    if (isReady) {
                        return;
                    }

                    isReady = true;
                    callback();
                };

                const timeoutId = setTimeout(() => {
                    done();
                }, 1500);

                document.fonts.ready
                    .then(() => warmupPromise)
                    .then(() => {
                        clearTimeout(timeoutId);
                        done();
                    })
                    .catch(() => {
                        clearTimeout(timeoutId);
                        done();
                    });
            } else {
                setTimeout(() => callback(), 150);
            }
        },
    });
});
