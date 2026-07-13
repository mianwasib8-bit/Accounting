/**
 * New Item Adder — category + items master
 * Enter key navigation via global app.js
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
      catCodeView: document.getElementById('cat_code_view'),
      code: document.getElementById('item_code'),
      name: document.getElementById('item_name'),
      packing: document.getElementById('packing'),
      sale: document.getElementById('sale_rate'),
      pur: document.getElementById('purchase_rate'),
      stock: document.getElementById('stock'),
      save: document.getElementById('btn-save-item'),
      update: document.getElementById('btn-update-item'),
      clear: document.getElementById('btn-clear-item'),
      del: document.getElementById('btn-delete-item'),
      tbody: document.getElementById('items-tbody'),
      btnCat: document.getElementById('btn-add-category'),
      catModal: document.getElementById('cat-modal'),
      catForm: document.getElementById('cat-form'),
      catCancel: document.getElementById('cat-cancel'),
      catTitle: document.getElementById('new_cat_title'),
      catCode: document.getElementById('new_cat_code'),
    };

    function money(n) {
      return Apex.money(n);
    }

    function stockFmt(n) {
      var x = Number(n) || 0;
      return x.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 3 });
    }

    function setEditMode(on) {
      if (els.save) els.save.disabled = !!on;
      if (els.update) els.update.disabled = !on;
      if (els.del) els.del.disabled = !on;
      if (els.category) els.category.disabled = !!on; // code tied to category
    }

    function clearForm() {
      els.id.value = '';
      if (els.category) {
        els.category.disabled = false;
        // keep selected category for faster multi-entry
      }
      els.code.value = '—';
      els.name.value = '';
      els.packing.value = '';
      els.sale.value = '0.00';
      els.pur.value = '0.00';
      els.stock.value = '0';
      setEditMode(false);
      previewCode();
      if (els.name) els.name.focus();
    }

    function previewCode() {
      var cid = parseInt(els.category.value, 10) || 0;
      var opt = els.category.options[els.category.selectedIndex];
      if (opt && opt.getAttribute('data-code') && els.catCodeView) {
        // show selected category code when chosen
        if (cid) els.catCodeView.textContent = opt.getAttribute('data-code');
      }
      if (!cid) {
        els.code.value = '—';
        return;
      }
      if (els.id.value) return; // editing — code fixed
      els.code.value = '…';
      Apex.api('/api/items.php?next_code=1&category_id=' + encodeURIComponent(cid)).then(function (d) {
        if (d.success) els.code.value = d.item_code;
        else els.code.value = '—';
      }).catch(function () {
        els.code.value = '—';
      });
    }

    function collectPayload() {
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
        '</tr>'
      );
    }

    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function bindRowClicks() {
      Apex.qsa('.item-row', els.tbody).forEach(function (tr) {
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', function () {
          els.id.value = tr.getAttribute('data-id') || '';
          els.category.value = tr.getAttribute('data-category-id') || '';
          els.category.disabled = true;
          els.code.value = tr.getAttribute('data-code') || '';
          els.name.value = tr.getAttribute('data-name') || '';
          els.packing.value = tr.getAttribute('data-packing') || '';
          els.sale.value = tr.getAttribute('data-sale') || '0';
          els.pur.value = tr.getAttribute('data-pur') || '0';
          els.stock.value = tr.getAttribute('data-stock') || '0';
          setEditMode(true);
          els.name.focus();
        });
      });
    }

    function reloadTable() {
      return Apex.api('/api/items.php').then(function (d) {
        if (!d.success) throw new Error(d.message || 'Failed to load items');
        var items = d.items || [];
        if (!items.length) {
          els.tbody.innerHTML = '<tr class="empty-row"><td colspan="6" class="empty-state">No items yet. Add from the left form.</td></tr>';
          return;
        }
        els.tbody.innerHTML = items.map(rowHtml).join('');
        bindRowClicks();
      });
    }

    function reloadCategories(selectId) {
      return Apex.api('/api/item_categories.php').then(function (d) {
        if (!d.success) throw new Error(d.message || 'Failed to load categories');
        var html = '<option value="">— Select category —</option>';
        (d.categories || []).forEach(function (c) {
          var sel = String(c.id) === String(selectId) ? ' selected' : '';
          html += '<option value="' + c.id + '" data-code="' + esc(c.code) + '"' + sel + '>' + esc(c.title) + '</option>';
        });
        els.category.innerHTML = html;
        if (els.catCode) els.catCode.value = d.next_code || '01';
        if (els.catCodeView && !selectId) els.catCodeView.textContent = d.next_code || '01';
        previewCode();
      });
    }

    // Category modal
    function openCatModal() {
      if (!els.catModal) return;
      els.catModal.classList.remove('hidden');
      Apex.api('/api/item_categories.php').then(function (d) {
        if (d.success && els.catCode) els.catCode.value = d.next_code;
      });
      if (els.catTitle) {
        els.catTitle.value = '';
        els.catTitle.focus();
      }
    }
    function closeCatModal() {
      if (els.catModal) els.catModal.classList.add('hidden');
    }

    if (els.btnCat) els.btnCat.addEventListener('click', openCatModal);
    if (els.catCancel) els.catCancel.addEventListener('click', closeCatModal);
    if (els.catModal) {
      els.catModal.addEventListener('click', function (e) {
        if (e.target === els.catModal) closeCatModal();
      });
    }

    if (els.catForm) {
      els.catForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var title = (els.catTitle.value || '').trim();
        if (!title) {
          Apex.toast('Category name required.', 'error');
          return;
        }
        Apex.api('/api/item_categories.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ title: title }),
        }).then(function (d) {
          if (!d.success) throw new Error(d.message || 'Save failed');
          Apex.toast('Category saved: ' + d.data.title, 'success');
          closeCatModal();
          return reloadCategories(d.data.id);
        }).catch(function (err) {
          Apex.toast(err.message || 'Error', 'error');
        });
      });
    }

    els.category.addEventListener('change', previewCode);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      // Save = create only
      if (els.id.value) {
        Apex.toast('Use Update for existing item, or Clear for new.', 'info');
        return;
      }
      var payload = collectPayload();
      els.save.disabled = true;
      Apex.api('/api/items.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      }).then(function (d) {
        els.save.disabled = false;
        if (!d.success) throw new Error(d.message || 'Save failed');
        Apex.toast('Item saved: ' + (d.data.item_name || d.data.item_code), 'success');
        clearForm();
        return reloadTable();
      }).catch(function (err) {
        els.save.disabled = false;
        Apex.toast(err.message || 'Error', 'error');
      });
    });

    if (els.update) {
      els.update.addEventListener('click', function () {
        var payload = collectPayload();
        if (!payload.id) {
          Apex.toast('Select an item from the list first.', 'error');
          return;
        }
        els.update.disabled = true;
        Apex.api('/api/items.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(Object.assign({}, payload, { _method: 'PUT' })),
        }).then(function (d) {
          els.update.disabled = false;
          setEditMode(true);
          if (!d.success) throw new Error(d.message || 'Update failed');
          Apex.toast('Item updated.', 'success');
          return reloadTable();
        }).catch(function (err) {
          els.update.disabled = false;
          setEditMode(true);
          Apex.toast(err.message || 'Error', 'error');
        });
      });
    }

    if (els.del) {
      els.del.addEventListener('click', function () {
        var id = parseInt(els.id.value, 10) || 0;
        if (!id) return;
        if (!confirm('Delete this item?')) return;
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

    if (els.clear) els.clear.addEventListener('click', clearForm);

    bindRowClicks();
    previewCode();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
