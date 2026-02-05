document.addEventListener('alpine:init', () => {
    window.Alpine.directive('top-scroll', (el, { expression }, { evaluate }) => {
        el.addEventListener('click', function () {
            window.animateScroll(evaluate(expression));
        });
    });
});
