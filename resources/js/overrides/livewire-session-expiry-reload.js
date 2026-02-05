document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419) {
                preventDefault();
                window.dispatch('livewire-session-timed-out');
                setTimeout(
                    () => window.location.reload(),
                    window.Alpine.$data(document.body).fastTransitionDurationInMs,
                );
            }
        });
    });
});
