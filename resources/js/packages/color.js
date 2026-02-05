import Color from 'color';
import { isCssVarName, isCssVarRef, normalizeCssVarName } from '../support/css-variables';

let colorProbe = null;

/**
 * Hidden element used to force browser to resolve any CSS color format
 * into computed rgb/rgba.
 */
const ensureColorProbe = () => {
    if (colorProbe) return colorProbe;

    colorProbe = document.createElement('span');
    Object.assign(colorProbe.style, {
        position: 'absolute',
        width: '0',
        height: '0',
        overflow: 'hidden',
        visibility: 'hidden',
        pointerEvents: 'none',
        inset: '0',
        color: 'transparent',
    });

    document.documentElement.appendChild(colorProbe);
    return colorProbe;
};

/**
 * Given ANY CSS color string, return the computed rgb/rgba string.
 * Examples:
 *  - "#ff0" -> "rgb(255, 255, 0)"
 *  - "oklch(...)" -> "rgb(...)"
 *  - "rgba(...)" -> "rgba(...)"
 *  - "var(--something)" -> resolved computed rgb/rgba
 */
export const cssResolveColor = (value, el = document.documentElement) => {
    if (!value) return '';

    const probe = ensureColorProbe();

    // Ensure probe is in the same variable scope as `el`
    const parent = probe.parentElement;
    if (el && parent !== el) {
        el.appendChild(probe);
    }

    probe.style.color = value;

    return getComputedStyle(probe).color.trim();
};

/**
 * Resolve a CSS variable into computed rgb/rgba string.
 */
export const cssVarColor = (name, el = document.documentElement) => {
    const varName = normalizeCssVarName(name);
    if (!varName.startsWith('--')) return '';

    // Always resolve via var(...) using the probe to avoid timing issues.
    return cssResolveColor(`var(${varName})`, el);
};

const clamp = (n, min, max) => Math.min(max, Math.max(min, n));
const toHex2 = (n) => clamp(Math.round(n), 0, 255).toString(16).padStart(2, '0');

const parseChannel255 = (x) => {
    const s = String(x).trim();
    if (s.endsWith('%')) return clamp((parseFloat(s) / 100) * 255, 0, 255);
    return clamp(parseFloat(s), 0, 255);
};

const parseAlpha01 = (x) => {
    const s = String(x).trim();
    if (s.endsWith('%')) return clamp(parseFloat(s) / 100, 0, 1);
    return clamp(parseFloat(s), 0, 1);
};

const parseHueDegrees = (value, unit) => {
    const h = parseFloat(value);

    if (unit === 'rad') return (h * 180) / Math.PI;
    if (unit === 'turn') return h * 360;
    if (unit === 'grad') return h * 0.9;

    return h;
};

const oklabToRgb = (L, a, b) => {
    const l_ = L + 0.3963377774 * a + 0.2158037573 * b;
    const m_ = L - 0.1055613458 * a - 0.0638541728 * b;
    const s_ = L - 0.0894841775 * a - 1.291485548 * b;

    const l = l_ ** 3;
    const m = m_ ** 3;
    const s = s_ ** 3;

    const rLin = 4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s;
    const gLin = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s;
    const bLin = -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s;

    const toSrgb = (x) => {
        const clamped = clamp(x, 0, 1);
        return clamped <= 0.0031308 ? 12.92 * clamped : 1.055 * Math.pow(clamped, 1 / 2.4) - 0.055;
    };

    return {
        r: Math.round(toSrgb(rLin) * 255),
        g: Math.round(toSrgb(gLin) * 255),
        b: Math.round(toSrgb(bLin) * 255),
    };
};

const parseOklab = (value) => {
    const v = String(value).trim().toLowerCase();
    if (!v.startsWith('oklab(')) return null;

    const match = v.match(
        /^oklab\(\s*([0-9.]+%?)\s+([+-]?[0-9.]+)\s+([+-]?[0-9.]+)(?:\s*\/\s*([0-9.]+%?))?\s*\)$/,
    );

    if (!match) return null;

    const L = match[1].endsWith('%') ? parseFloat(match[1]) / 100 : parseFloat(match[1]);
    const a = parseFloat(match[2]);
    const b = parseFloat(match[3]);
    const alpha = match[4] != null ? parseAlpha01(match[4]) : 1;

    return { L, a, b, alpha };
};

const parseOklch = (value) => {
    const v = String(value).trim().toLowerCase();
    if (!v.startsWith('oklch(')) return null;

    const match = v.match(
        /^oklch\(\s*([0-9.]+%?)\s+([0-9.]+%?)\s+([0-9.]+)(deg|rad|turn|grad)?(?:\s*\/\s*([0-9.]+%?))?\s*\)$/,
    );

    if (!match) return null;

    const L = match[1].endsWith('%') ? parseFloat(match[1]) / 100 : parseFloat(match[1]);
    const C = match[2].endsWith('%') ? parseFloat(match[2]) / 100 : parseFloat(match[2]);
    const h = parseHueDegrees(match[3], match[4]);
    const alpha = match[5] != null ? parseAlpha01(match[5]) : 1;

    return { L, C, h, alpha };
};

const toRgbaFromOklabOrOklch = (value) => {
    const parsedOklab = parseOklab(value);
    if (parsedOklab) {
        const { r, g, b } = oklabToRgb(parsedOklab.L, parsedOklab.a, parsedOklab.b);
        return { r, g, b, alpha: parsedOklab.alpha };
    }

    const parsedOklch = parseOklch(value);
    if (parsedOklch) {
        const a = parsedOklch.C * Math.cos((parsedOklch.h * Math.PI) / 180);
        const b = parsedOklch.C * Math.sin((parsedOklch.h * Math.PI) / 180);
        const { r, g, b: blue } = oklabToRgb(parsedOklch.L, a, b);
        return { r, g, b: blue, alpha: parsedOklch.alpha };
    }

    return null;
};

/**
 * Parse a color into { r, g, b, a }
 * Supports:
 * - "#rgb" "#rgba" "#rrggbb" "#rrggbbaa"
 * - "rrggbb" (no #)
 * - "rgb(10 20 30)"
 * - "rgb(10, 20, 30)"
 * - "rgba(10, 20, 30, 0.5)"
 * - "rgb(10 20 30 / 50%)"
 * - "10 20 30" or "10, 20, 30"
 */
const parseCssColor = (value) => {
    if (!value) return null;

    const v = String(value).trim().toLowerCase();
    if (!v) return null;

    if (v.startsWith('oklch(') || v.startsWith('oklab(') || v.startsWith('color(')) {
        return null;
    }

    const rawHex = v.startsWith('#') ? v.slice(1) : v;
    if (/^([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(rawHex)) {
        let hex = rawHex;

        if (hex.length === 3 || hex.length === 4) {
            hex = hex
                .split('')
                .map((c) => c + c)
                .join('');
        }

        const r = parseInt(hex.slice(0, 2), 16);
        const g = parseInt(hex.slice(2, 4), 16);
        const b = parseInt(hex.slice(4, 6), 16);

        let a = 1;
        if (hex.length === 8) a = parseInt(hex.slice(6, 8), 16) / 255;

        return { r, g, b, a };
    }

    const rgbFunc = v.match(
        /^rgba?\(\s*([0-9.]+%?)\s*[, ]\s*([0-9.]+%?)\s*[, ]\s*([0-9.]+%?)(?:\s*[/,]\s*([0-9.]+%?))?\s*\)$/,
    );

    if (rgbFunc) {
        const r = parseChannel255(rgbFunc[1]);
        const g = parseChannel255(rgbFunc[2]);
        const b = parseChannel255(rgbFunc[3]);
        const a = rgbFunc[4] != null ? parseAlpha01(rgbFunc[4]) : 1;

        return { r: Math.round(r), g: Math.round(g), b: Math.round(b), a };
    }

    const nums = v.match(/[0-9.]+%?/g);
    if (nums && nums.length >= 3) {
        const r = parseChannel255(nums[0]);
        const g = parseChannel255(nums[1]);
        const b = parseChannel255(nums[2]);
        return { r: Math.round(r), g: Math.round(g), b: Math.round(b), a: 1 };
    }

    return null;
};

const colorFromString = (value) => {
    if (!value) return null;
    try {
        return Color(value);
    } catch {
        const parsedOkl = toRgbaFromOklabOrOklch(value);
        if (parsedOkl) {
            return Color(
                `rgba(${parsedOkl.r}, ${parsedOkl.g}, ${parsedOkl.b}, ${parsedOkl.alpha})`,
            );
        }

        const parsed = parseCssColor(value);
        if (!parsed) return null;

        try {
            return Color(`rgba(${parsed.r}, ${parsed.g}, ${parsed.b}, ${parsed.a})`);
        } catch {
            return null;
        }
    }
};

const toColorFromInput = (value, el = document.documentElement) => {
    if (!value) return null;

    const trimmed = String(value).trim();
    if (!trimmed) return null;

    if (isCssVarName(trimmed)) {
        const varName = normalizeCssVarName(trimmed);
        return toColor(`var(${varName})`, el);
    }

    if (isCssVarRef(trimmed)) {
        return toColor(trimmed, el);
    }

    return toColor(trimmed, el);
};

/**
 * Safely create a Color instance from any CSS color input (raw or computed).
 * - Supports var(--x) via resolving
 * - Supports oklch(...) via browser resolving -> rgb
 */
export const toColor = (value, el = document.documentElement) => {
    if (!value) return null;

    try {
        const computed = cssResolveColor(value, el);
        return colorFromString(computed);
    } catch {
        return null;
    }
};

/**
 * Like toColor(), but reads from CSS variable name (--x)
 */
export const cssVarToColor = (name, el = document.documentElement) => {
    const computed = cssVarColor(name, el);
    if (!computed) return null;

    try {
        return colorFromString(computed);
    } catch {
        return null;
    }
};

/**
 * Converts any CSS color (hex/rgb/rgba/oklch/var(...)/etc) to hex string.
 * - default includes alpha if present (Color uses #RRGGBB or #RRGGBBAA depending)
 */
export const toHex = (value, el = document.documentElement, { alpha = true } = {}) => {
    const c = toColor(value, el);
    if (!c) return '';

    // color.hex() drops alpha; color.hexa() includes alpha
    return alpha ? c.hexa() : c.hex();
};

export const toRgb = (value, el = document.documentElement) => {
    const c = toColor(value, el);
    if (!c) return '';

    // "rgb(r, g, b)"
    return c.rgb().string();
};

export const toRgba = (value, el = document.documentElement) => {
    const c = toColor(value, el);
    if (!c) return '';

    // "rgba(r, g, b, a)"
    return c.rgb().string(); // Color() will output rgba if alpha < 1
};

export const toRgbChannels = (value, el = document.documentElement) => {
    const c = toColor(value, el);
    if (!c) return '';

    const { r, g, b } = c.rgb().object();
    return `${r}, ${g}, ${b}`;
};

export const toRgbaChannels = (value, el = document.documentElement) => {
    const c = toColor(value, el);
    if (!c) return '';

    const { r, g, b, alpha } = c.rgb().object();
    return `${r}, ${g}, ${b}, ${alpha}`;
};

/**
 * CSS var converters (your requested API style)
 */
export const cssVarToHex = (name, el = document.documentElement, { alpha = false } = {}) => {
    const c = cssVarToColor(name, el);
    if (!c) return '';

    const hex = alpha ? c.hexa() : c.hex();
    return hex.toLowerCase();
};

export const cssVarToRgb = (name, el = document.documentElement) => {
    const c = cssVarToColor(name, el);
    if (!c) return '';
    return c.rgb().string();
};

export const cssVarToRgba = (name, el = document.documentElement) => {
    const c = cssVarToColor(name, el);
    if (!c) return '';
    return c.rgb().string();
};

export const cssVarToRgbChannels = (name, el = document.documentElement) => {
    const c = cssVarToColor(name, el);
    if (!c) return '';
    const { r, g, b } = c.rgb().object();
    return `${r}, ${g}, ${b}`;
};

export const cssVarToRgbaChannels = (name, el = document.documentElement) => {
    const c = cssVarToColor(name, el);
    if (!c) return '';
    const { r, g, b, alpha } = c.rgb().object();
    return `${r}, ${g}, ${b}, ${alpha}`;
};

/**
 * Optional: normalize any input into a usable computed "rgb/rgba"
 * (useful when you're mixing hex / oklch / vars)
 */
export const toComputedCssRgb = (value, el = document.documentElement) => {
    return cssResolveColor(value, el);
};

export const cssVarToComputedCssRgb = (name, el = document.documentElement) => {
    return cssVarColor(name, el);
};

const DEFAULT_SHADOW_LAYERS = [
    { x: 0, y: 6, blur: 13, spread: 0, alpha: 0.1 },
    { x: 0, y: 24, blur: 24, spread: 0, alpha: 0.09 },
    { x: 0, y: 55, blur: 33, spread: 0, alpha: 0.05 },
    { x: 0, y: 97, blur: 39, spread: 0, alpha: 0.01 },
    { x: 0, y: 152, blur: 43, spread: 0, alpha: 0 },
];

export const makeBoxShadowFromColor = (input, options = {}) => {
    const { el = document.documentElement, layers = DEFAULT_SHADOW_LAYERS } = options;

    const c = toColorFromInput(input, el);
    if (!c) return 'none';

    const { r, g, b } = c.rgb().object();
    const baseAlpha = c.alpha();

    return layers
        .map((layer) => {
            const x = (layer.x ?? 0) + 'px';
            const y = (layer.y ?? 0) + 'px';
            const blur = (layer.blur ?? 0) + 'px';
            const spread = (layer.spread ?? 0) + 'px';
            const alpha = clamp((layer.alpha ?? 1) * baseAlpha, 0, 1);

            return `${x} ${y} ${blur} ${spread} rgba(${r}, ${g}, ${b}, ${alpha})`;
        })
        .join(', ');
};

/**
 * Your old function equivalents rewritten using color
 */
export const cssRgbToHex = (value, { alpha = false } = {}) => {
    if (!value) return '';
    try {
        const c = colorFromString(value);
        if (!c) {
            const parsed = parseCssColor(value);
            if (!parsed) return '';
            const hex = `#${toHex2(parsed.r)}${toHex2(parsed.g)}${toHex2(parsed.b)}`;
            return hex.toLowerCase();
        }

        const hex = alpha ? c.hexa() : c.hex();
        return hex.toLowerCase();
    } catch {
        return '';
    }
};

export const cssHexToRgb = (hex) => {
    if (!hex) return '';
    try {
        const c = colorFromString(hex);
        if (!c) return '';
        const { r, g, b } = c.rgb().object();
        return `${r}, ${g}, ${b}`;
    } catch {
        return '';
    }
};

/**
 * Expose globals like you did (optional)
 */
window.cssVarColor = cssVarColor;
window.cssResolveColor = cssResolveColor;

window.toColor = toColor;
window.toHex = toHex;
window.toRgb = toRgb;
window.toRgba = toRgba;

window.cssVarToColor = cssVarToColor;
window.cssVarToHex = cssVarToHex;
window.cssVarToRgb = cssVarToRgb;
window.cssVarToRgba = cssVarToRgba;

window.cssVarToRgbChannels = cssVarToRgbChannels;
window.cssVarToRgbaChannels = cssVarToRgbaChannels;

window.toComputedCssRgb = toComputedCssRgb;
window.cssVarToComputedCssRgb = cssVarToComputedCssRgb;

window.cssRgbToHex = cssRgbToHex;
window.cssHexToRgb = cssHexToRgb;
window.makeBoxShadowFromColor = makeBoxShadowFromColor;
