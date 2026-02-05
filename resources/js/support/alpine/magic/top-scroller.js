document.addEventListener('alpine:init', () => {
    window.Alpine.magic('topScroll', () => {
        return (offset) => window.animateScroll(offset);
    });
});
