import {
    readAthkarOverridesFromStorage,
    writeAthkarOverridesToStorage,
} from '../athkar-app-overrides';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('athkarAppManager', (config) => ({
        componentId: String(config.componentId ?? ''),
        nativeMobileRuntime: Boolean(config.nativeMobileRuntime ?? false),
        cardRepelHoldDurationInMs: 700,
        cardRepelReleaseDurationInMs: 420,
        cardPressMoveThresholdInPixels: 8,
        activeCardPointer: null,
        pendingCardOpenTimer: null,
        pendingCardOpenId: null,
        cardInteractionAbortController: null,
        cardOrderAbortController: null,
        pendingCardOrderFrame: null,
        isApplyingCardOrder: false,
        init() {
            this.hydrateOverridesFromStorage();
            this.registerOverridesPersistenceListener();
            this.registerCardInteractionListeners();
            this.registerCardOrderSync();
        },
        shouldRestrictSortHandles() {
            return true;
        },
        managerSortConfig() {
            const shouldUseBodyFallback = this.nativeMobileRuntime || Boolean(this.$store?.bp?.is?.('base'));

            return {
                animation: 150,
                forceFallback: true,
                fallbackOnBody: shouldUseBodyFallback,
                fallbackTolerance: 0,
                handle: '[data-athkar-sort-handle]',
                invertSwap: true,
                swapThreshold: 0.7,
                invertedSwapThreshold: 0.7,
            };
        },
        registerCardInteractionListeners() {
            this.cardInteractionAbortController?.abort();

            const controller = new AbortController();
            const { signal } = controller;

            this.cardInteractionAbortController = controller;

            this.$root.addEventListener(
                'pointerdown',
                (event) => {
                    const dragHandle = event.target instanceof Element
                        ? event.target.closest('[data-athkar-sort-handle]')
                        : null;
                    const cardElement = this.resolveManagedCard(event.target);

                    if (!(dragHandle instanceof Element) || !cardElement) {
                        return;
                    }

                    this.markCardClickHandled(cardElement);
                },
                { signal, passive: true, capture: true },
            );

            this.$root.addEventListener(
                'pointerdown',
                (event) => {
                    const cardElement = this.resolveManagedCard(event.target);

                    if (!cardElement || this.isExcludedCardTarget(event.target)) {
                        return;
                    }

                    this.startCardRepel(cardElement, event);
                    this.activeCardPointer = {
                        pointerId: event.pointerId,
                        cardId: String(cardElement.dataset.athkarCardId ?? ''),
                        startX: event.clientX,
                        startY: event.clientY,
                        moved: false,
                        leftCard: false,
                    };
                },
                { signal, passive: true },
            );

            window.addEventListener(
                'pointermove',
                (event) => {
                    if (!this.activeCardPointer || this.activeCardPointer.pointerId !== event.pointerId) {
                        return;
                    }

                    const cardElement = this.cardElementById(this.activeCardPointer.cardId);

                    if (!cardElement) {
                        this.activeCardPointer = null;

                        return;
                    }

                    const deltaX = event.clientX - this.activeCardPointer.startX;
                    const deltaY = event.clientY - this.activeCardPointer.startY;
                    const moveDistance = Math.hypot(deltaX, deltaY);

                    if (moveDistance >= this.cardPressMoveThresholdInPixels) {
                        this.activeCardPointer.moved = true;
                    }

                    const hoveredCard = this.resolveManagedCard(document.elementFromPoint(event.clientX, event.clientY));
                    this.activeCardPointer.leftCard = hoveredCard !== cardElement;

                    if (this.activeCardPointer.moved || this.activeCardPointer.leftCard) {
                        this.cancelCardRepel(cardElement);
                    }
                },
                { signal, passive: true },
            );

            window.addEventListener(
                'pointerup',
                (event) => {
                    this.finishCardPointerInteraction(event);
                },
                { signal, passive: true },
            );

            window.addEventListener(
                'pointercancel',
                (event) => {
                    this.finishCardPointerInteraction(event, true);
                },
                { signal, passive: true },
            );

            this.$root.addEventListener(
                'click',
                (event) => {
                    const cardElement = this.resolveManagedCard(event.target);

                    if (!cardElement || this.isExcludedCardTarget(event.target)) {
                        return;
                    }

                    if (this.shouldSkipCardClick(cardElement)) {
                        event.preventDefault();

                        return;
                    }

                    this.startCardRepel(cardElement, event);
                    this.endCardRepel(cardElement, event);
                    this.queueCardOpen(cardElement);
                    event.preventDefault();
                },
                { signal },
            );
        },
        registerCardOrderSync() {
            this.cardOrderAbortController?.abort();

            const controller = new AbortController();
            const { signal } = controller;

            this.cardOrderAbortController = controller;

            const grid = this.resolveCardsGrid();

            if (!grid) {
                return;
            }

            const schedule = () => this.scheduleCardOrderSync();

            schedule();

            const observer = new MutationObserver(() => {
                if (this.isApplyingCardOrder) {
                    return;
                }

                schedule();
            });

            observer.observe(grid, { childList: true });

            signal.addEventListener('abort', () => observer.disconnect(), { once: true });

            window.addEventListener('resize', schedule, { passive: true, signal });
            window.addEventListener('orientationchange', schedule, { passive: true, signal });

            if (typeof this.$watch === 'function') {
                this.$watch(() => this.$store?.bp?.current, () => schedule());
            }
        },
        scheduleCardOrderSync() {
            if (this.pendingCardOrderFrame !== null) {
                return;
            }

            this.pendingCardOrderFrame = window.requestAnimationFrame(() => {
                this.pendingCardOrderFrame = null;
                this.applyRtlCardOrder();
            });
        },
        applyRtlCardOrder() {
            if (document.body.classList.contains('sorting')) {
                return;
            }

            const grid = this.resolveCardsGrid();

            if (!grid) {
                return;
            }

            const cards = Array.from(grid.querySelectorAll('[data-athkar-manager-card]'));

            if (cards.length < 2) {
                return;
            }

            const columns = this.detectGridColumns(cards);

            if (columns <= 1) {
                return;
            }

            const orderedCards = cards
                .map((card, index) => ({
                    card,
                    index: Number(card.dataset.athkarOrderIndex ?? index),
                }))
                .sort((a, b) => a.index - b.index)
                .map(({ card }) => card);

            const reordered = [];
            for (let offset = 0; offset < orderedCards.length; offset += columns) {
                const slice = orderedCards.slice(offset, offset + columns).reverse();
                reordered.push(...slice);
            }

            const currentIds = cards.map((card) => card.dataset.athkarCardId);
            const nextIds = reordered.map((card) => card.dataset.athkarCardId);

            if (currentIds.join('|') === nextIds.join('|')) {
                return;
            }

            this.isApplyingCardOrder = true;

            const fragment = document.createDocumentFragment();
            reordered.forEach((card) => fragment.appendChild(card));
            grid.appendChild(fragment);

            this.isApplyingCardOrder = false;
        },
        detectGridColumns(cards) {
            const threshold = 8;
            const sorted = cards
                .map((card) => ({ card, top: card.getBoundingClientRect().top }))
                .sort((a, b) => a.top - b.top);

            if (sorted.length === 0) {
                return 1;
            }

            const firstTop = sorted[0].top;
            return sorted.filter((item) => Math.abs(item.top - firstTop) <= threshold).length || 1;
        },
        resolveCardsGrid() {
            return this.$root?.querySelector?.('[wire\\:sort="reorderAthkar"]') ?? null;
        },
        resolveManagedCard(target) {
            if (!(target instanceof Element)) {
                return null;
            }

            const cardElement = target.closest('[data-athkar-manager-card]');

            if (!(cardElement instanceof HTMLElement) || !this.$root.contains(cardElement)) {
                return null;
            }

            return cardElement;
        },
        cardElementById(cardId) {
            if (!cardId) {
                return null;
            }

            const cardElement = this.$root.querySelector(`[data-athkar-card-id="${cardId}"]`);

            return cardElement instanceof HTMLElement ? cardElement : null;
        },
        isExcludedCardTarget(target) {
            return target instanceof Element && Boolean(
                target.closest('[data-athkar-sort-handle]') ||
                target.closest('[wire\\:sort\\:ignore]'),
            );
        },
        shouldSkipCardClick(cardElement) {
            return Number(cardElement.dataset.athkarSkipClickUntil ?? 0) > Date.now();
        },
        markCardClickHandled(cardElement) {
            cardElement.dataset.athkarSkipClickUntil = String(Date.now() + 400);
        },
        finishCardPointerInteraction(event, shouldCancel = false) {
            if (!this.activeCardPointer || this.activeCardPointer.pointerId !== event.pointerId) {
                return;
            }

            const cardElement = this.cardElementById(this.activeCardPointer.cardId);

            if (!cardElement) {
                this.activeCardPointer = null;

                return;
            }

            const releasedCard = this.resolveManagedCard(document.elementFromPoint(event.clientX, event.clientY));
            const shouldOpen =
                !shouldCancel &&
                !this.activeCardPointer.moved &&
                !this.activeCardPointer.leftCard &&
                releasedCard === cardElement;

            if (shouldOpen) {
                this.endCardRepel(cardElement, event);
                this.markCardClickHandled(cardElement);
                this.queueCardOpen(cardElement);
            } else {
                this.cancelCardRepel(cardElement);
            }

            this.activeCardPointer = null;
        },
        setCardRepelOrigin(cardElement, event) {
            const rect = cardElement.getBoundingClientRect();
            const fallbackX = rect.left + (rect.width / 2);
            const fallbackY = rect.top + (rect.height / 2);
            const clientX = Number.isFinite(Number(event.clientX)) ? Number(event.clientX) : fallbackX;
            const clientY = Number.isFinite(Number(event.clientY)) ? Number(event.clientY) : fallbackY;
            const localX = Math.max(0, Math.min(rect.width, clientX - rect.left));
            const localY = Math.max(0, Math.min(rect.height, clientY - rect.top));
            const distanceToFarthestCorner = Math.max(
                Math.hypot(localX, localY),
                Math.hypot(rect.width - localX, localY),
                Math.hypot(localX, rect.height - localY),
                Math.hypot(rect.width - localX, rect.height - localY),
            );
            const baseRadius = 8;
            const animationScale = Math.max(14, Math.ceil((distanceToFarthestCorner + 24) / baseRadius));

            cardElement.style.setProperty('--athkar-repel-x', `${localX}px`);
            cardElement.style.setProperty('--athkar-repel-y', `${localY}px`);
            cardElement.style.setProperty('--athkar-repel-scale', String(animationScale));
            cardElement.style.setProperty('--athkar-repel-hold-duration', `${this.cardRepelHoldDurationInMs}ms`);
            cardElement.style.setProperty('--athkar-repel-release-duration', `${this.cardRepelReleaseDurationInMs}ms`);
        },
        startCardRepel(cardElement, event) {
            this.setCardRepelOrigin(cardElement, event);

            if (cardElement._athkarRepelTimer) {
                clearTimeout(cardElement._athkarRepelTimer);
            }

            cardElement._athkarRepelStartedAt = Date.now();
            cardElement.setAttribute('data-athkar-press', 'hold');
        },
        endCardRepel(cardElement, event) {
            if (cardElement.getAttribute('data-athkar-press') !== 'hold') {
                return;
            }

            this.setCardRepelOrigin(cardElement, event);
            const startedAt = Number(cardElement._athkarRepelStartedAt ?? 0);
            const elapsed = startedAt > 0 ? Date.now() - startedAt : 0;
            const holdRatio = Math.max(0, Math.min(1, elapsed / this.cardRepelHoldDurationInMs));
            const releaseStartScale = 0.08 + (0.54 * holdRatio);
            const releaseStartOpacity = holdRatio >= 0.86
                ? Math.max(0, 0.12 - ((holdRatio - 0.86) * 0.85))
                : 0.34 - (0.14 * holdRatio);

            cardElement.style.setProperty('--athkar-repel-release-start-scale', String(releaseStartScale));
            cardElement.style.setProperty('--athkar-repel-release-start-opacity', String(releaseStartOpacity));
            cardElement.setAttribute('data-athkar-press', 'release');

            if (cardElement._athkarRepelTimer) {
                clearTimeout(cardElement._athkarRepelTimer);
            }

            cardElement._athkarRepelTimer = window.setTimeout(() => {
                cardElement.removeAttribute('data-athkar-press');
            }, this.cardRepelReleaseDurationInMs + 28);
        },
        cancelCardRepel(cardElement) {
            cardElement.removeAttribute('data-athkar-press');
            cardElement._athkarRepelStartedAt = 0;

            if (cardElement._athkarRepelTimer) {
                clearTimeout(cardElement._athkarRepelTimer);
            }
        },
        queueCardOpen(cardElement) {
            const nextCardId = Number(cardElement.dataset.athkarCardId ?? 0);

            if (!Number.isInteger(nextCardId) || nextCardId < 1) {
                return;
            }

            if (this.pendingCardOpenTimer) {
                clearTimeout(this.pendingCardOpenTimer);
            }

            this.pendingCardOpenId = nextCardId;
            const managerScrollSnapshot = this.captureManagerScrollPosition();

            this.pendingCardOpenTimer = window.setTimeout(async () => {
                try {
                    const wire = this.$wire ?? window.Livewire?.find?.(this.componentId);

                    if (wire && typeof wire.call === 'function') {
                        await wire.call('openEditAthkar', nextCardId);
                    } else if (wire) {
                        await wire.openEditAthkar(nextCardId);
                    }
                } finally {
                    this.pendingCardOpenId = null;
                    this.pendingCardOpenTimer = null;
                    this.restoreManagerScrollPosition(managerScrollSnapshot);
                }
            }, this.cardRepelReleaseDurationInMs + 48);
        },
        captureManagerScrollPosition() {
            const managerContentElement = document.querySelector('.fi-modal.fi-modal-open .fi-modal-content');

            if (!(managerContentElement instanceof HTMLElement)) {
                return null;
            }

            return {
                element: managerContentElement,
                top: managerContentElement.scrollTop,
            };
        },
        restoreManagerScrollPosition(snapshot) {
            if (!snapshot || !(snapshot.element instanceof HTMLElement)) {
                return;
            }

            const scrollTop = Number(snapshot.top ?? 0);
            const apply = () => {
                if (!snapshot.element.isConnected) {
                    return;
                }

                snapshot.element.scrollTop = scrollTop;
            };

            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(apply);
            });
        },
        hydrateOverridesFromStorage() {
            const overrides = readAthkarOverridesFromStorage();

            if (typeof this.$wire?.syncAthkarOverrides === 'function') {
                this.$wire.syncAthkarOverrides(overrides);
            }
        },
        registerOverridesPersistenceListener() {
            window.addEventListener('athkar-manager-overrides-persisted', (event) => {
                const detail = event?.detail ?? {};
                const eventComponentId = String(detail?.componentId ?? '');

                if (eventComponentId !== this.componentId) {
                    return;
                }

                const overrides = Array.isArray(detail?.overrides) ? detail.overrides : [];
                const normalizedOverrides = writeAthkarOverridesToStorage(overrides);

                window.dispatchEvent(
                    new CustomEvent('athkar-overrides-updated', {
                        detail: { overrides: normalizedOverrides },
                    }),
                );
            });
        },
    }));
});
