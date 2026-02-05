(function () {
    window.__colorSchemeTokens = {
        light: '--background',
        dark: '--background-dark',
    };

    const isDark = JSON.parse(localStorage.getItem('colorScheme_darkMode'));
    const themeToken = isDark ? window.__colorSchemeTokens.dark : window.__colorSchemeTokens.light;

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.backgroundColor = `var(${themeToken})`;
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
})();
