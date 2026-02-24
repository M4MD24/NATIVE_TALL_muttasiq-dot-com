document.addEventListener('livewire:init', () => {
    Livewire.hook('component.init', ({ component }) => {
        if (component.name !== 'color-scheme-switcher') return;

        const raw = localStorage.getItem('colorScheme_darkMode');
        const isDarkModeOn = raw !== null ? Boolean(JSON.parse(raw)) : null;

        queueMicrotask(() => {
            Livewire.dispatchTo('color-scheme-switcher', 'color-scheme-initialized', {
                isDarkModeOn,
            });
        });
    });
});

document.addEventListener('alpine:init', () => {
    window.Alpine.store('colorScheme', {
        isDark: window.Alpine.$persist(null).as('colorScheme_darkMode'),
        bodyBackgroundTokens: window.__colorSchemeTokens,
        themeColorTokens: window.__colorSchemeTokens,

        get isDarkModeOn() {
            return Boolean(this.isDark);
        },
        get bodyBackgroundColor() {
            return window.cssVarToHex(
                this.isDarkModeOn
                    ? this.bodyBackgroundTokens.dark
                    : this.bodyBackgroundTokens.light,
            );
        },
        get bodyBackgroundHexes() {
            return {
                light: window.cssVarToHex(this.themeColorTokens.light),
                dark: window.cssVarToHex(this.themeColorTokens.dark),
            };
        },
        toggle() {
            this.isDark = !this.isDarkModeOn;
        },
    });

    window.Alpine.effect(() => {
        (async () => {
            document.documentElement.classList.add('color-scheme-switching');

            const colorSchemeStore = window.Alpine.store('colorScheme');
            const isDarkModeOn = colorSchemeStore.isDarkModeOn;

            document.documentElement.classList.toggle('dark', isDarkModeOn);
            document.documentElement.style.colorScheme = isDarkModeOn ? 'dark' : 'light';
            document.documentElement.style.backgroundColor = colorSchemeStore.bodyBackgroundColor;

            window.Livewire.dispatchTo('color-scheme-switcher', 'color-scheme-toggled', {
                isDarkModeOn: isDarkModeOn,
            });

            await new Promise((resolve) => requestAnimationFrame(resolve));

            await Promise.all(document.getAnimations({ subtree: true }).map((a) => a.finished));

            document.documentElement.classList.remove('color-scheme-switching');
        })();
    });
});
