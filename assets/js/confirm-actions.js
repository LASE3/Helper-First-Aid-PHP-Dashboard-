document.addEventListener('submit', function (event) {
    const form = event.target;

    if (form.classList.contains('js-confirm-delete')) {
        const ok = window.confirm('Are you sure you want to delete this item?');
        if (!ok) event.preventDefault();
    }

    if (form.classList.contains('js-confirm-save')) {
        const ok = window.confirm('Are you sure you want to save this edit?');
        if (!ok) event.preventDefault();
    }
});
