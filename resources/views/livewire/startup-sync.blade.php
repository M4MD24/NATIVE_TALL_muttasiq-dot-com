<div
    class="hidden"
    data-testid="startup-sync-component"
    aria-hidden="true"
>
    @teleport('body')
        <div
            class="z-999998 fixed inset-0 cursor-progress transition-opacity duration-200"
            data-testid="startup-sync-shield"
            aria-hidden="true"
            x-cloak
            x-data="{ isPending: window.__startupSyncResolved !== true }"
            x-init="if (window.__startupSyncResolved === true) isPending = false"
            x-on:startup-sync-resolved.window="isPending = false"
            x-show="isPending"
        ></div>
    @endteleport
</div>
