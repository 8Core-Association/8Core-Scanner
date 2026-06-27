
document.addEventListener('submit', function(e) {
    var form = e.target;
    if (!form.classList.contains('action-form')) return;

    var action = form.querySelector('input[name="action"]').value;
    if (action === 'delete_requested') {
        if (!confirm('Označiti za brisanje? Fizičko brisanje izvršava root worker kasnije.')) {
            e.preventDefault();
        }
    }

    if (action === 'quarantine_requested') {
        if (!confirm('Označiti za karantenu? Fizičko micanje izvršava root worker kasnije.')) {
            e.preventDefault();
        }
    }
});
