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

    const colorSchemeStore = window.Alpine.store('colorScheme');

    document.addEventListener('livewire:init', () => {
        window.Livewire.dispatchTo('home', 'color-scheme-initialized', {
            isDarkModeOn: colorSchemeStore.isDarkModeOn,
        });
    });

    window.Alpine.effect(() => {
        const colorSchemeStore = window.Alpine.store('colorScheme');
        const isDarkModeOn = colorSchemeStore.isDarkModeOn;

        document.documentElement.classList.toggle('dark', isDarkModeOn);
        document.documentElement.style.colorScheme = isDarkModeOn ? 'dark' : 'light';
        document.documentElement.style.backgroundColor = colorSchemeStore.bodyBackgroundColor;

        window.Livewire.dispatchTo('home', 'color-scheme-toggled', {
            isDarkModeOn: isDarkModeOn,
        });
    });
});
