document.addEventListener('click', function (event) {
    document.querySelectorAll('.top-popover[open]').forEach(function (popover) {
        if (!popover.contains(event.target)) {
            popover.removeAttribute('open');
        }
    });
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.top-popover[open]').forEach(function (popover) {
            popover.removeAttribute('open');
        });
    }
});
