import fitty from 'fitty';

window.fitty = fitty;

const minMainTextSizeSettingKey = 'minimum_main_text_size';
const minMainTextSizeMinimum = 10;
const minMainTextSizeMaximum = 20;
const minMainTextSizeDefault = 16;

const resolveDefaultMinSize = () => {
    if (typeof window === 'undefined') {
        return minMainTextSizeDefault;
    }

    const defaults = window.athkarSettingsDefaults;
    const raw = defaults?.[minMainTextSizeSettingKey];
    const numeric = Number.isFinite(Number(raw)) ? Number(raw) : minMainTextSizeDefault;
    const rounded = Number.isFinite(numeric) ? Math.trunc(numeric) : minMainTextSizeDefault;

    return Math.min(minMainTextSizeMaximum, Math.max(minMainTextSizeMinimum, rounded));
};

const resolveAvailableBoxSpace = (boxElement, safePaddingX, safePaddingY) => {
    const width = Math.max(0, boxElement.clientWidth - safePaddingX);
    const height = Math.max(0, boxElement.clientHeight - safePaddingY);

    return { width, height };
};

const resolveMaxTextSize = (baseSize, maxScale) => {
    if (!Number.isFinite(maxScale) || maxScale <= 1) {
        return baseSize;
    }

    return Math.max(baseSize, baseSize * maxScale);
};

const ensureFittyInstance = (textElement, minSize, maxSize) => {
    const storedMin = Number.parseFloat(textElement.dataset.fittyMinSize ?? '0');
    const storedMax = Number.parseFloat(textElement.dataset.fittyMaxSize ?? '0');

    if (textElement._fittyInstance && storedMin === minSize && storedMax === maxSize) {
        return textElement._fittyInstance;
    }

    if (textElement._fittyInstance?.unsubscribe) {
        textElement._fittyInstance.unsubscribe();
    }

    const instance = window.fitty(textElement, {
        minSize,
        maxSize,
        multiLine: true,
        observeMutations: false,
        observeWindow: false,
    });

    textElement._fittyInstance = instance;
    textElement.dataset.fittyMinSize = String(minSize);
    textElement.dataset.fittyMaxSize = String(maxSize);

    return instance;
};

const fitTextToBox = (textElement, availableWidth, availableHeight, minSize, maxSize, step) => {
    let size = Number.parseFloat(getComputedStyle(textElement).fontSize);

    if (!Number.isFinite(size) || !availableWidth || !availableHeight) {
        return;
    }

    const fits = () => {
        return (
            textElement.scrollHeight <= availableHeight && textElement.scrollWidth <= availableWidth
        );
    };

    if (!fits()) {
        size = Math.max(minSize, Math.min(maxSize, size));
    }

    let low = minSize;
    let high = maxSize;
    let best = Math.max(minSize, Math.min(size, maxSize));

    for (let i = 0; i < 14; i += 1) {
        const mid = (low + high) / 2;
        textElement.style.fontSize = `${mid}px`;

        if (fits()) {
            best = mid;
            low = mid;
        } else {
            high = mid;
        }
    }

    const rounded = Math.max(minSize, Math.min(maxSize, best));
    const normalizedStep = step > 0 ? step : 0.25;
    let snapped = Math.round(rounded / normalizedStep) * normalizedStep;
    snapped = Math.max(minSize, Math.min(maxSize, snapped));

    textElement.style.fontSize = `${snapped}px`;

    // Keep shrinking to avoid bottom/edge overflow after snapping.
    let guard = 0;

    while (!fits() && snapped > minSize && guard < 24) {
        snapped = Math.max(minSize, snapped - normalizedStep);
        textElement.style.fontSize = `${snapped}px`;
        guard += 1;
    }

    if (!fits()) {
        textElement.style.fontSize = `${minSize}px`;
    }
};

const fitTextInBox = ({
    textElement,
    boxElement,
    minSize = resolveDefaultMinSize(),
    maxScale = 1.2,
    baseSizeOverride = null,
    step = 0.5,
    safePaddingX = 0,
    safePaddingY = 0,
    shouldApplyFittyClass = true,
}) => {
    if (!textElement || !boxElement) {
        return;
    }

    const { width: availableWidth, height: availableHeight } = resolveAvailableBoxSpace(
        boxElement,
        Number.isFinite(safePaddingX) ? Math.max(0, safePaddingX) : 0,
        Number.isFinite(safePaddingY) ? Math.max(0, safePaddingY) : 0,
    );

    if (!window.fitty || !availableWidth || !availableHeight) {
        if (shouldApplyFittyClass) {
            textElement.classList.add('is-fit');
        }

        return;
    }

    const hasBaseSizeOverride = Number.isFinite(Number(baseSizeOverride));

    if (hasBaseSizeOverride) {
        textElement.style.fontSize = `${Number(baseSizeOverride)}px`;
    } else {
        textElement.style.fontSize = '';
    }

    const baseSize = Number.parseFloat(getComputedStyle(textElement).fontSize);

    if (!Number.isFinite(baseSize)) {
        return;
    }

    const maxSize = resolveMaxTextSize(baseSize, maxScale);
    textElement.style.fontSize = `${baseSize}px`;

    const instance = ensureFittyInstance(textElement, minSize, maxSize);

    if (instance?.fit) {
        instance.fit();
    }

    fitTextToBox(textElement, availableWidth, availableHeight, minSize, maxSize, step);

    if (shouldApplyFittyClass) {
        requestAnimationFrame(() => {
            textElement.classList.add('is-fit');
        });
    }
};

export { fitTextInBox };
