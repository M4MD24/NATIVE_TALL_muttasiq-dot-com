const normalizeCssVarName = (name) => {
    if (!name) return '';

    const trimmed = String(name).trim();
    if (trimmed.startsWith('var(')) {
        const match = trimmed.match(/var\(\s*(--[^,\s)]+)/);
        return match?.[1] ?? '';
    }

    return trimmed;
};

const cssVar = (name, el = document.documentElement) => {
    const varName = normalizeCssVarName(name);
    if (!varName.startsWith('--')) return '';

    return getComputedStyle(el).getPropertyValue(varName).trim();
};

const isCssVarName = (value) => typeof value === 'string' && value.trim().startsWith('--');

const isCssVarRef = (value) => typeof value === 'string' && value.trim().startsWith('var(');

window.normalizeCssVarName = normalizeCssVarName;
window.cssVar = cssVar;
window.isCssVarName = isCssVarName;
window.isCssVarRef = isCssVarRef;

export { cssVar, isCssVarName, isCssVarRef, normalizeCssVarName };
