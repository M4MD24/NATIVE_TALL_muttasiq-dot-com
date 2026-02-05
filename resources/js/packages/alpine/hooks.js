import { useHover, useFocus } from '@ryangjchandler/alpine-hooks';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(useHover);
    window.Alpine.plugin(useFocus);
});
