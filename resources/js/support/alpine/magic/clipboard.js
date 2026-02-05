document.addEventListener('alpine:init', () => {
    window.Alpine.magic('clipboard', () => {
        return (subject) => navigator.clipboard.writeText(subject);
    });
});
