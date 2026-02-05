<x-app>
    <div
        class="flex h-full flex-1 flex-col"
        x-cloak
        x-transition.opacity
        x-show="isBodyVisible"
        x-data="{
            lock: null,
            isSettingsOpen: false,
            activeView: $persist('main-menu').as('app-active-view'),
            views: {
                'main-menu': {
                    title: @js(view_title(\App\Services\Enums\ViewName::MainMenu)),
                    isOpen: true,
                },
            },
            init() {
                this.applyViewState(this.activeView);
            },
            runHashAction(callback) {
                if (window.__hashActionBypassLock) {
                    if (typeof callback === 'function') {
                        callback();
                    }
                    return;
                }
        
                if (this.lock?.run) {
                    this.lock.run(callback);
                    return;
                }
        
                if (typeof callback === 'function') {
                    callback();
                }
            },
            applyViewState(nextView) {
                const view = this.views?.[nextView] ? nextView : 'main-menu';
        
                Object.keys(this.views).forEach((key) => {
                    this.views[key].isOpen = key === view;
                });
        
                this.activeView = view;
        
                if (this.views[view]) {
                    document.title = this.views[view].title;
                }
            },
        }"
        x-bind:data-hash-default="activeView"
        x-hash-actions="{
            '#main-menu': () => runHashAction(() => {
                $dispatch('switch-view', { to: 'main-menu' });
            }),
            '#toggle-color-scheme': () => runHashAction(() => {
                $store.colorScheme.toggle();
            }),
            '#settings': () => runHashAction(() => {
                $dispatch('open-settings-modal');
            }),
        }"
        x-on:switch-view.window="applyViewState($event.detail?.to)"
    >
        <x-buttons-stack x-bind:data-respecting-stack="$store.bp.current === 'base'">
            @if (!is_platform('mobile'))
                @include('partials.home-button')
            @endif
            <livewire:color-scheme-switcher />
            <livewire:settings />
        </x-buttons-stack>

        <main class="fixed inset-0 mt-16 grid place-items-center sm:mt-0 dark:text-white">
            @include('partials.main-menu')
        </main>
    </div>
</x-app>
