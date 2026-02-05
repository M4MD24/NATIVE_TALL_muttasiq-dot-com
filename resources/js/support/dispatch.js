window.dispatch = (name, detail = {}) => {
    window.dispatchEvent(new CustomEvent(name, { detail }));
};
