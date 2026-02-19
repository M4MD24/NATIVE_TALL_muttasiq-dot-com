<div
    class="{{ \Illuminate\Support\Arr::toCssClasses([
        'bottom-3' => !is_platform('mobile'),
        'bottom-7' => is_platform('mobile'),
        'fixed inset-x-0 z-30 flex w-full max-w-full justify-center overflow-hidden px-4 opacity-0 transition-opacity sm:w-auto sm:max-w-none sm:px-6 pointer-events-none',
    ]) }}"
    data-testid="copyright-version-shell"
    x-bind:class="{
        'opacity-100!': views['main-menu'].isOpen || views['athkar-app-gate'].isOpen,
    }"
    x-data="{
        isVisible: false,
        isHovering: false,
        isTouching: false,
        waitDuration: 3000,
        visibleDuration: 3000,
        waitTimeoutId: null,
        hideTimeoutId: null,
        isTouchDevice() {
            return Boolean($store.bp?.hasTouch ?? $store.bp?.isTouch?.());
        },
        clearLoopTimers() {
            if (this.waitTimeoutId) {
                clearTimeout(this.waitTimeoutId);
                this.waitTimeoutId = null;
            }
            if (this.hideTimeoutId) {
                clearTimeout(this.hideTimeoutId);
                this.hideTimeoutId = null;
            }
        },
        queueNextReveal(delay = this.waitDuration) {
            this.clearLoopTimers();
            if (this.isHovering || this.isTouching) {
                this.isVisible = true;
                return;
            }
            this.waitTimeoutId = setTimeout(() => {
                this.isVisible = true;
                this.hideTimeoutId = setTimeout(() => {
                    if (this.isHovering || this.isTouching) {
                        return;
                    }
                    this.isVisible = false;
                    this.queueNextReveal();
                }, this.visibleDuration);
            }, delay);
        },
        holdVisible() {
            this.clearLoopTimers();
            this.isVisible = true;
        },
        releaseVisible() {
            this.isVisible = false;
            this.queueNextReveal();
        },
        releaseVisibleAfter(delay) {
            this.clearLoopTimers();
            this.isVisible = true;
            this.hideTimeoutId = setTimeout(() => {
                if (this.isHovering || this.isTouching) {
                    return;
                }
                this.releaseVisible();
            }, delay);
        },
        handleMouseEnter() {
            if (this.isTouchDevice()) {
                return;
            }
            this.isHovering = true;
            this.holdVisible();
        },
        handleMouseLeave() {
            if (this.isTouchDevice()) {
                return;
            }
            this.isHovering = false;
            this.releaseVisible();
        },
        handleTouchStart() {
            if (!this.isTouchDevice()) {
                return;
            }
            this.isTouching = true;
            this.holdVisible();
        },
        handleTouchEnd() {
            if (!this.isTouchDevice()) {
                return;
            }
            this.isTouching = false;
            this.releaseVisibleAfter(this.visibleDuration);
        },
        init() {
            this.queueNextReveal();
        },
        destroy() {
            this.clearLoopTimers();
        },
    }"
    x-on:mouseenter="handleMouseEnter()"
    x-on:mouseleave="handleMouseLeave()"
    x-on:touchstart.passive="handleTouchStart()"
    x-on:touchend.passive="handleTouchEnd()"
    x-on:touchcancel.passive="handleTouchEnd()"
>
    <div
        class="relative rounded-2xl border border-white/70 bg-gray-100/30 px-4 py-3 text-[0.8rem] text-gray-600 opacity-0 ring-1 ring-gray-200/70 transition-opacity duration-500 ease-out sm:px-6 sm:py-4 sm:text-[1rem] dark:border-white/10 dark:bg-gray-900/20 dark:text-gray-300 dark:ring-white/10"
        data-testid="copyright-version-panel"
        x-bind:class="isVisible && 'opacity-100!'"
    >
        <p class="whitespace-nowrap">
            جميع الحقوق محفوظة •
            متسق @ <span x-text="window.dayjs().calendar('hijri').format('YYYY')"></span> هـ
            • النسخة
            <button
                class="inline rounded-sm font-semibold text-gray-800 underline decoration-gray-400/80 underline-offset-4 transition-colors hover:text-gray-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400/60 dark:text-gray-100 dark:decoration-gray-400/60 dark:hover:text-white dark:focus-visible:ring-gray-200/40"
                data-testid="copyright-version-button"
                type="button"
                x-bind:class="isVisible && 'pointer-events-auto!'"
                x-on:click="$dispatch('open-control-panel-modal', { tab: 'updates' })"
            >
                v{{ config('app.custom.app_version') }}
            </button>
        </p>
    </div>
</div>
