@props([
    'onClickCallback' => null,
    'iconClasses' => '',
    'iconName',
    'caption',
])

@php
    $locked = !$onClickCallback;
@endphp

<div
    class="flex h-20 w-20 cursor-pointer select-none items-center justify-center rounded-xl border border-transparent transition-colors will-change-[border-color,box-shadow]"
    data-main-menu-item
    data-caption="{{ $caption }}"
    data-icon-name="{{ $iconName }}"
    data-on-click-callback="{{ $onClickCallback ?? '' }}"
    data-locked="{{ $locked ? 'true' : 'false' }}"
    x-data="{
        hovered: $useHover($el),
        isTouchActive: false,
        isTouching: false,
        isForcedActive: false,
        isLockedActive: false,
        get isActive() {
            return this.isForcedActive;
        },
        shadow: makeBoxShadowFromColor('--primary-500'),
        shadowDark: makeBoxShadowFromColor('--primary-100'),
        caption: @js($caption),
        iconName: @js($iconName),
        onClickCallback: @js($onClickCallback),
        locked: @js($locked),
    }"
    x-on:main-menu-touch-state.window="
        isTouching = $event.detail?.isTouching ?? false;
        isTouchActive = $event.detail?.element === $el;
    "
    x-on:main-menu-active-state.window="isForcedActive = $event.detail?.element === $el"
    x-on:main-menu-lock-state.window="
        isLockedActive = $event.detail?.element === $el && $event.detail?.active;
    "
    x-on:mouseenter="
        if (caption) {
            $dispatch('main-menu-item-enter', { caption, iconName, onClickCallback, locked, element: $el, source: 'hover' });
        }
    "
    x-on:mouseleave="$dispatch('main-menu-item-leave')"
    x-on:click="
        if (caption) {
            $dispatch('main-menu-item-enter', { caption, iconName, onClickCallback, locked, element: $el, source: 'click' });
        }
        $dispatch('main-menu-item-click', { caption, iconName, onClickCallback, locked, element: $el });
    "
    x-on:focus="
        if (caption) {
            $dispatch('main-menu-item-enter', { caption, iconName, onClickCallback, locked, element: $el, source: 'focus' });
        }
    "
    x-on:blur="$dispatch('main-menu-item-leave')"
    x-effect="$el.style.boxShadow = isActive ? ($store.colorScheme.isDarkModeOn ? shadowDark : shadow) : 'none'"
    x-bind:class="{ 'fill-primary-500 dark:border-primary-200!': isActive }"
>
    <div class="relative flex h-8 w-8 items-center justify-center">
        <x-icon
            class="{{ twMerge('will-change-[transform,opacity,filter] relative z-10 fill-primary-500 dark:fill-primary-200 h-8 w-8 transform-gpu transition-[opacity,scale,filter]', $iconClasses) }}"
            x-bind:class="{
                'scale-[0.88]! opacity-[0.48] grayscale-[1]': containerHovered && !isActive,
                'opacity-0 scale-[0.92]': isLockedActive,
            }"
            :name="$iconName"
        />

        @if ($locked)
            <span
                class="{{ twMerge('will-change-[transform,opacity] absolute inset-0 z-0 flex h-8 w-8 items-center justify-center transform-gpu transition-[opacity,transform]', $iconClasses) }}"
                x-cloak
                x-data="{ ready: false }"
                x-init="() => setTimeout((ready = true), 100);"
                x-show="ready"
                x-bind:class="{
                    'opacity-100 scale-100': isLockedActive,
                    'opacity-0 scale-[0.85]': !isLockedActive,
                }"
            >
                <x-icon
                    class="fill-primary-500 dark:fill-primary-200 transform-fill h-8 w-8 origin-center"
                    data-lock-icon
                    :name="'entypo.lock'"
                />
            </span>
        @endif
    </div>
</div>
