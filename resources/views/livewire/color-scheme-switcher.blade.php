<div
    class="fixed start-10 top-7 z-30 sm:top-5 md:top-8"
    data-stack-item
    wire:ignore
    x-transition
    x-show="!isControlPanelOpen && !isAthkarManagerOpen"
    x-init="() => (lock = $livewireLock($wire, defaultTransitionDurationInMs, true))"
>
    <x-action-button
        data-testid="color-scheme-switch-button"
        x-on:click="$hashAction('toggle-color-scheme')"
        :useInvertedStyle="true"
    >
        <x-slot:icons-slot>
            <x-icon
                class="text-primary-600 dark:text-primary-100 absolute top-1/2 left-1/2 h-8 w-8 -translate-x-1/2 -translate-y-1/2 -rotate-45 shrink-0 transition will-change-[color]"
                name="heroicon-s-moon"
                x-bind:class="{ 'text-primary-100! dark:text-primary-600!': hovered }"
                x-show="!$store.colorScheme.isDarkModeOn"
            />
            <x-icon
                class="text-primary-600 dark:text-primary-100 absolute top-1/2 left-1/2 h-8 w-8 -translate-x-1/2 -translate-y-1/2 -rotate-45 shrink-0 transition will-change-[color]"
                name="heroicon-s-sun"
                x-bind:class="{ 'text-primary-600!': hovered }"
                x-cloak
                x-show="$store.colorScheme.isDarkModeOn"
            />
        </x-slot:icons-slot>
    </x-action-button>
</div>
