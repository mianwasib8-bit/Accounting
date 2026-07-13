/**
 * Items screen — category dropdown, code 2+2, rates, stock, row icons
 */
(function () {
  'use strict';

  function boot() {
    if (typeof window.Apex === 'undefined') {
      setTimeout(boot, 40);
      return;
    }
    var form = document.getElementById('item-form');
    if (!form) return;

    var els = {
      id: document.getElementById('item_id'),
      category: document.getElementById('category_id'),
      code: document.getElementById('item_code'),
      name: document.getElementById('item_name'),
      packing: document.getElementById('packing'),
      sale: document.getElementById('sale_rate'),
      pur: document.getElementById('purchase_rate'),
      stock: document.getElementById('stock'),
      save: document.getElementById('btn-save-item'),
      update: document.getElementById('btn-update-item'),
      clear: document.getElementById('btn-clear-item'),
      tbody: document.getElementById('items-tbody'),
    };

    function money(n) {
      return Apex.money(n);
    }
    function stockFmt(n) {
      var x = Number(n) || 0;
      return x.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 3 });
    }
    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function setEdit(on) {
      els.save.disabled = !!on;
      els.update.disabled = !on;
      els.category.disabled = !!on;
    }

    function clearForm() {
      els.id.value = '';
      els.category.disabled = false;
      els.code.value = '—';
      els.name.value = '';
      els.packing.value = '';
      els.sale.value = '0.00';
      els.pur.value = '0.00';
      els.stock.value = '0';
      setEdit(false);
      previewCode();
      els.name.focus();
    }

    function previewCode() {
      var cid = parseInt(els.category.value, 10) || 0;
      if (!cid) {
        els.code.value = '—';
        return;
      }
      if (els.id.value) return;
      els.code.value = '…';
      Apex.api('/api/items.php?next_code=1&category_id=' + encodeURIComponent(cid)).then(function (d) {
        els.code.value = d.success ? d.item_code : '—';
      }).catch(function () {
        els.code.value = '—';
      });
    }

    function payload() {
      return {
        id: parseInt(els.id.value, 10) || 0,
        category_id: parseInt(els.category.value, 10) || 0,
        item_name: els.name.value.trim(),
        packing: els.packing.value.trim(),
        sale_rate: parseFloat(els.sale.value) || 0,
        purchase_rate: parseFloat(els.pur.value) || 0,
        stock: parseFloat(els.stock.value) || 0,
      };
    }

    function rowHtml(item) {
      return (
        '<tr class="item-row" data-id="' + item.id + '"' +
        ' data-category-id="' + item.category_id + '"' +
        ' data-code="' + esc(item.item_code) + '"' +
        ' data-name="' + esc(item.item_name) + '"' +
        ' data-packing="' + esc(item.packing || '') + '"' +
        ' data-sale="' + item.sale_rate + '"' +
        ' data-pur="' + item.purchase_rate + '"' +
        ' data-stock="' + item.stock + '">' +
        '<td class="num" style="color:var(--brand);font-weight:700">' + esc(item.item_code) + '</td>' +
        '<td><strong>' + esc(item.item_name) + '</strong></td>' +
        '<td>' + esc(item.packing || '') + '</td>' +
        '<td class="text-right num">' + money(item.sale_rate) + '</td>' +
        '<td class="text-right num">' + money(item.purchase_rate) + '</td>' +
        '<td class="text-right num">' + stockFmt(item.stock) + '</td>' +
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

    function fillFromRow(tr) {
      els.id.value = tr.getAttribute('data-id') || '';
      els.category.value = tr.getAttribute('data-category-id') || '';
      els.category.disabled = true;
      els.code.value = tr.getAttribute('data-code') || '';
      els.name.value = tr.getAttribute('data-name') || '';
      els.packing.value = tr.getAttribute('data-packing') || '';
      els.sale.value = tr.getAttribute('data-sale') || '0';
      els.pur.value = tr.getAttribute('data-pur') || '0';
      els.stock.value = tr.getAttribute('data-stock') || '0';
      setEdit(true);
      els.name.focus();
    }

    function bindRows() {
      Apex.qsa('.item-row', els.tbody).forEach(function (tr) {
        var edit = tr.querySelector('.btn-edit');
        var del = tr.querySelector('.btn-del');
        if (edit) {
          edit.addEventListener('click', function (e) {
            e.stopPropagation();
            fillFromRow(tr);
          });
        }
        if (del) {
          del.addEventListener('click', function (e) {
            e.stopPropagation();
            var id = parseInt(tr.getAttribute('data-id'), 10) || 0;
            if (!id || !confirm('Delete this item?')) return;
            Apex.api('/api/items.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ _method: 'DELETE', id: id }),
            }).then(function (d) {
              if (!d.success) throw new Error(d.message || 'Delete failed');
              Apex.toast('Item deleted.', 'success');
              clearForm();
              return reloadTable();
            }).catch(function (err) {
              Apex.toast(err.message || 'Error', 'error');
            });
          });
        }
      });
    }

    function reloadTable() {
      return Apex.api('/api/items.php').then(function (d) {
        if (!d.success) throw new Error(d.message || 'Failed to load');
        var list = d.items || [];
        if (!list.length) {
          els.tbody.innerHTML = '<tr class="empty-row"><td colspan="7" class="empty-state">No items yet.</td></tr>';
          return;
        }
        els.tbody.innerHTML = list.map(rowHtml).join('');
        bindRows();
      });
    }

    els.category.addEventListener('change', previewCode);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (els.id.value) {
        Apex.toast('Use Update for existing item.', 'info');
        return;
      }
      var p = payload();
      els.save.disabled = true;
      Apex.api('/api/items.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(p),
      }).then(function (d) {
        els.save.disabled = false;
        if (!d.success) throw new Error(d.message || 'Save failed');
        Apex.toast('Item saved.', 'success');
        clearForm();
        return reloadTable();
      }).catch(function (err) {
        els.save.disabled = false;
        Apex.toast(err.message || 'Error', 'error');
      });
    });

    els.update.addEventListener('click', function () {
      var p = payload();
      if (!p.id) return;
      els.update.disabled = true;
      Apex.api('/api/items.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({}, p, { _method: 'PUT' })),
      }).then(function (d) {
        if (!d.success) throw new Error(d.message || 'Update failed');
        Apex.toast('Item updated.', 'success');
        clearForm();
        return reloadTable();
      }).catch(function (err) {
        els.update.disabled = false;
        setEdit(true);
        Apex.toast(err.message || 'Error', 'error');
      });
    });

    els.clear.addEventListener('click', clearForm);
    bindRows();
    previewCode();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
