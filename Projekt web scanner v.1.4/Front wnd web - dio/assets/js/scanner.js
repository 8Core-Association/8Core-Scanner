
function toggleRow(id) {
  var row = document.querySelector('.data-row[data-id="' + id + '"]');
  var detail = document.getElementById('detail-' + id);
  if (!row || !detail) return;

  var expanded = row.classList.contains('row-expanded');
  row.classList.toggle('row-expanded', !expanded);
  detail.classList.toggle('hidden', expanded);
}

function toggleDrop(id, e) {
  e.stopPropagation();
  var el = document.getElementById(id);
  if (!el) return;
  var isOpen = el.classList.contains('open');
  closeAllDrops();
  if (!isOpen) el.classList.add('open');
}

function closeAllDrops() {
  document.querySelectorAll('.action-drop.open').forEach(function(d) {
    d.classList.remove('open');
  });
}

document.addEventListener('click', closeAllDrops);

document.addEventListener('submit', function(e) {
  var btn = e.submitter;
  if (!btn) return;

  if (btn.classList.contains('act-delete')) {
    if (!confirm('Označiti za brisanje? Fizičko brisanje izvršava root worker kasnije.')) {
      e.preventDefault();
    }
    return;
  }

  if (btn.classList.contains('act-quarantine')) {
    if (!confirm('Označiti za karantenu? Fizičko micanje izvršava root worker kasnije.')) {
      e.preventDefault();
    }
  }
});
