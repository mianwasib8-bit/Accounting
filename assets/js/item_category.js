/**
 * Item Categories screen
 */
(function () {
  'use strict';

  function boot() {
    if (typeof window.Apex === 'undefined') {
      setTimeout(boot, 40);
      return;
    }
    var form = document.getElementById('cat-form');
    if (!form) return;

    var idEl = document.getElementById('cat_id');
    var codeEl = document.getElementById('cat_code');
    var titleEl = document.getElementById('cat_title');
    var btnSave = document.getElementById('btn-cat-save');
    var btnUpdate = document.getElementById('btn-cat-update');
    var btnClear = document.getElementById('btn-cat-clear');
    var tbody = document.getElementById('cat-tbody');

    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function setEdit(on) {
      btnSave.disabled = !!on;
      btnUpdate.disabled = !on;
    }

    function clearForm(nextCode) {
      idEl.value = '';
      titleEl.value = '';
      if (nextCode) codeEl.value = nextCode;
      setEdit(false);
      titleEl.focus();
    }

    function rowHtml(c) {
      return (
        '<tr data-id="' + c.id + '" data-code="' + esc(c.code) + '" data-title="' + esc(c.title) + '">' +
        '<td class="num" style="color:var(--brand);font-weight:700">' + esc(c.code) + '</td>' +
        '<td><strong>' + esc(c.title) + '</strong></td>' +
        '<td class="text-center">' +
        '<button type="button" class="icon-action btn-edit" title="Edit">' +
        '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>' +
        '</button>' +
        '<button type="button" class="icon-action btn-del" title="Delete">' +
        '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
        '</button>' +
        '</td></tr>'
      );
    }

    function bindRows() {
      Apex.qsa('#cat-tbody tr[data-id]').forEach(function (tr) {
        var edit = tr.querySelector('.btn-edit');
        var del = tr.querySelector('.btn-del');
        if (edit) {
          edit.addEventListener('click', function (e) {
            e.stopPropagation();
            idEl.value = tr.getAttribute('data-id');
            codeEl.value = tr.getAttribute('data-code');
            titleEl.value = tr.getAttribute('data-title');
            setEdit(true);
            titleEl.focus();
          });
        }
        if (del) {
          del.addEventListener('click', function (e) {
            e.stopPropagation();
            var id = tr.getAttribute('data-id');
            if (!confirm('Delete this category?')) return;
            Apex.api('/api/item_categories.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ _method: 'DELETE', id: parseInt(id, 10) }),
            }).then(function (d) {
              if (!d.success) throw new Error(d.message || 'Delete failed');
              Apex.toast('Category deleted.', 'success');
              return reload();
            }).catch(function (err) {
              Apex.toast(err.message || 'Error', 'error');
            });
          });
        }
      });
    }

    function reload() {
      return Apex.api('/api/item_categories.php').then(function (d) {
        if (!d.success) throw new Error(d.message || 'Load failed');
        var list = d.categories || [];
        if (!list.length) {
          tbody.innerHTML = '<tr class="empty-row"><td colspan="3" class="empty-state">No categories yet.</td></tr>';
        } else {
          tbody.innerHTML = list.map(rowHtml).join('');
          bindRows();
        }
        clearForm(d.next_code || '01');
      });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (idEl.value) {
        Apex.toast('Use Update for existing category.', 'info');
        return;
      }
      var title = titleEl.value.trim();
      if (!title) {
        Apex.toast('Enter category title.', 'error');
        return;
      }
      btnSave.disabled = true;
      Apex.api('/api/item_categories.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: title }),
      }).then(function (d) {
        btnSave.disabled = false;
        if (!d.success) throw new Error(d.message || 'Save failed');
        Apex.toast('Category saved.', 'success');
        return reload();
      }).catch(function (err) {
        btnSave.disabled = false;
        Apex.toast(err.message || 'Error', 'error');
      });
    });

    btnUpdate.addEventListener('click', function () {
      var id = parseInt(idEl.value, 10) || 0;
      var title = titleEl.value.trim();
      if (!id || !title) return;
      btnUpdate.disabled = true;
      Apex.api('/api/item_categories.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ _method: 'PUT', id: id, title: title }),
      }).then(function (d) {
        if (!d.success) throw new Error(d.message || 'Update failed');
        Apex.toast('Category updated.', 'success');
        return reload();
      }).catch(function (err) {
        btnUpdate.disabled = false;
        setEdit(true);
        Apex.toast(err.message || 'Error', 'error');
      });
    });

    btnClear.addEventListener('click', function () {
      Apex.api('/api/item_categories.php').then(function (d) {
        clearForm(d.next_code || '01');
      });
    });

    bindRows();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
