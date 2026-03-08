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
        init() {
            this.hydrateOverridesFromStorage();
            this.registerOverridesPersistenceListener();
            this.registerCardInteractionListeners();
        },
        shouldRestrictSortHandles() {
            return true;
        },
        managerSortConfig() {
            return {
                animation: 150,
                forceFallback: true,
                fallbackOnBody: true,
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
