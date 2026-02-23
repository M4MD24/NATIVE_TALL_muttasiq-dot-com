document.addEventListener('alpine:init', () => {
    const root = document.documentElement;
    const colorSchemeSwitchingClass = 'color-scheme-switching';
    let guardFrameId = null;
    let lastAppliedIsDarkModeOn = null;
    let isLivewireInitialized = false;
    let lastDispatchedIsDarkModeOn = null;

    const releaseColorSchemeSwitchGuard = () => {
        guardFrameId = window.requestAnimationFrame(() => {
            guardFrameId = window.requestAnimationFrame(() => {
                root.classList.remove(colorSchemeSwitchingClass);
            });
        });
    };

    const applyColorSchemeSwitchGuard = () => {
        root.classList.add(colorSchemeSwitchingClass);

        if (guardFrameId !== null) {
            window.cancelAnimationFrame(guardFrameId);
        }

        releaseColorSchemeSwitchGuard();
    };

    const dispatchColorSchemeEvent = (eventName, isDarkModeOn) => {
        if (!isLivewireInitialized || lastDispatchedIsDarkModeOn === isDarkModeOn) {
            return;
        }

        lastDispatchedIsDarkModeOn = isDarkModeOn;

        window.Livewire.dispatchTo('color-scheme-switcher', eventName, {
            isDarkModeOn: isDarkModeOn,
        });
    };

    window.Alpine.store('colorScheme', {
        isDark: window.Alpine.$persist(null).as('colorScheme_darkMode'),
        bodyBackgroundTokens: window.__colorSchemeTokens,
        themeColorTokens: window.__colorSchemeTokens,
        themeColorHexes: {
            light: window.cssVarToHex(window.__colorSchemeTokens.light),
            dark: window.cssVarToHex(window.__colorSchemeTokens.dark),
        },

        get isDarkModeOn() {
            return Boolean(this.isDark);
        },
        get bodyBackgroundToken() {
            return this.isDarkModeOn
                ? this.bodyBackgroundTokens.dark
                : this.bodyBackgroundTokens.light;
        },
        get bodyBackgroundColor() {
            return `var(${this.bodyBackgroundToken})`;
        },
        get bodyBackgroundHexes() {
            return this.themeColorHexes;
        },
        toggle() {
            this.isDark = !this.isDarkModeOn;
        },
    });

    const colorSchemeStore = window.Alpine.store('colorScheme');

    document.addEventListener('livewire:init', () => {
        isLivewireInitialized = true;

        dispatchColorSchemeEvent('color-scheme-initialized', colorSchemeStore.isDarkModeOn);
    });

    window.Alpine.effect(() => {
        const colorSchemeStore = window.Alpine.store('colorScheme');
        const isDarkModeOn = colorSchemeStore.isDarkModeOn;

        if (lastAppliedIsDarkModeOn === isDarkModeOn) {
            return;
        }

        lastAppliedIsDarkModeOn = isDarkModeOn;

        applyColorSchemeSwitchGuard();

        root.classList.toggle('dark', isDarkModeOn);
        root.style.colorScheme = isDarkModeOn ? 'dark' : 'light';
        root.style.backgroundColor = colorSchemeStore.bodyBackgroundColor;

        dispatchColorSchemeEvent('color-scheme-toggled', isDarkModeOn);
    });
});
