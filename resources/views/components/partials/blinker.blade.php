<div
    class="z-999999 fixed bottom-[calc(var(--inset-bottom,0px)*-1)] left-[calc(var(--inset-left,0px)*-1)] right-[calc(var(--inset-right,0px)*-1)] top-[calc(var(--inset-top,0px)*-1)] transition-[opacity,background-color] ease-in will-change-[opacity,background-color]"
    x-ref="blinker"
    x-on:livewire-session-timed-out.window="blink(false, true)"
    x-bind:style="{
        backgroundColor: $store.colorScheme.bodyBackgroundColor,
        transitionDuration: (defaultTransitionDurationInMs + 'ms'),
    }"
    x-bind:class="{
        'opacity-0 pointer-events-none': !isBlinkerShown,
    }"
></div>
