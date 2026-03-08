<div
    x-data="{ jsErrorReporterModalId: @js('fi-' . $this->getId() . '-action-0') }"
    x-on:open-js-error-report-modal.window="$wire.openReportModal($event.detail ?? {})"
    x-on:x-modal-opened.window="
        if ($event.detail?.id === jsErrorReporterModalId) {
            window.dispatch('js-error-report-modal-opened');
        }
    "
    x-on:close-modal.window="
        if ($event.detail?.id === jsErrorReporterModalId) {
            window.dispatch('js-error-report-modal-closed');
        }
    "
    x-on:close-modal-quietly.window="
        if ($event.detail?.id === jsErrorReporterModalId) {
            window.dispatch('js-error-report-modal-closed');
        }
    "
>
    <x-filament-actions::modals />
</div>

@assets
    <x-partials.scripts.mobile-js-errors-handler />
@endassets
