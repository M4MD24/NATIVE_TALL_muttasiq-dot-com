function animateScroll(offset) {
    const speed = 1;

    var position = window.scrollY;
    var progress = 0;

    let animation = function animation() {
        progress += 1;
        position -= progress * speed;
        window.scrollTo(0, position + offset);

        if (position > 0) {
            requestAnimationFrame(animation);
        }
    };

    requestAnimationFrame(animation);
}

window.animateScroll = animateScroll;
