// Kutumb -- small progressive-enhancement touches, no framework.

document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss flash alerts after a few seconds.
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = window.bootstrap && window.bootstrap.Alert
                ? window.bootstrap.Alert.getOrCreateInstance(alert)
                : null;
            if (bsAlert) {
                bsAlert.close();
            } else {
                alert.remove();
            }
        }, 5000);
    });

    // Inline category rename: toggles the name input from read-only to
    // editable, and swaps the "rename" link for a "save" submit button.
    document.querySelectorAll('.category-rename-form').forEach(function (form) {
        var input = form.querySelector('.rename-input');
        var editBtn = form.querySelector('.rename-edit-btn');
        var saveBtn = form.querySelector('.rename-save-btn');
        if (!input || !editBtn || !saveBtn) return;

        editBtn.addEventListener('click', function () {
            input.readOnly = false;
            input.focus();
            input.select();
            editBtn.classList.add('d-none');
            saveBtn.classList.remove('d-none');
        });
    });
});
