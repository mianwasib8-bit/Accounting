/**
 * Cash CRV/CPV — amount-only lines, min 2 rows
 * Row 1: party/expense · Row 2: cash/bank (user manages)
 * No top fixed cash account
 */
(function () {
  'use strict';

  function boot() {
    if (typeof window.Apex === 'undefined') {
      setTimeout(boot, 40);
      return;
    }
    var form = document.getElementById('voucher-form');
    if (!form) return;

    var typeEl = document.getElementById('voucher_type');
    if (!typeEl) return;
    var type = typeEl.value;

    var els = {
      ref: document.getElementById('voucher_ref'),
      seq: document.getElementById('sequence_no'),
      date: document.getElementById('voucher_date'),
      body: document.getElementById('lines-body'),
      total: document.getElementById('total-amount'),
      save: document.getElementById('btn-save'),
      add: document.getElementById('btn-add-row'),
    };
    if (!els.body || !els.date) return;

    var accounts = [];

    function esc(s) {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }
    function money(n) {
      return Apex.money(n);
    }
    function parseAmt(v) {
      var n = parseFloat(String(v).replace(/,/g, ''));
      return isNaN(n) || n < 0 ? 0 : n;
    }
    function optionsHtml(selected) {
      var h = '<option value="">— Select —</option>';
      accounts.forEach(function (a) {
        var sel = String(a.id) === String(selected) ? ' selected' : '';
// Dropdown shows TITLE only (no code prefix)
      h +=
        '<option value="' +
        a.id +
        '" data-title="' +
        esc(a.title) +
        '" data-code="' +
        esc(a.code || '') +
        '" data-balance="' +
        a.balance +
        '" data-type="' +
        esc(a.type || '') +
        '"' +
        sel +
        '>' +
        esc(a.title || a.label) +
        '</option>';
      });
      return h;
    }
    function renumber() {
      Apex.qsa('.line-row', els.body).forEach(function (tr, i) {
        var c = tr.querySelector('.line-seq');
        if (c) c.textContent = String(i + 1);
      });
    }

    function addRow() {
      var tr = document.createElement('tr');
      tr.className = 'line-row';
      tr.innerHTML =
        '<td class="line-seq num" style="font-weight:700;color:var(--muted)">1</td>' +
        '<td><select class="select account-select input-sm">' +
        optionsHtml('') +
        '</select></td>' +
        '<td><input class="input input-sm title-view" type="text" readonly tabindex="-1" placeholder="—" /></td>' +
        '<td><input class="input input-sm narr-input" type="text" maxlength="500" placeholder="Narration" /></td>' +
        '<td><input class="input input-sm amount-input" type="number" min="0" step="0.01" placeholder="0.00" style="text-align:right" /></td>' +
        '<td class="text-right num prev-bal" style="font-weight:600;color:var(--muted)">0.00</td>' +
        '<td class="text-center"><button type="button" class="icon-btn btn-remove" title="Remove" style="width:32px;height:32px;color:var(--muted)">' +
        '<svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
        '</button></td>';
      els.body.appendChild(tr);
      bindRow(tr);
      renumber();
      recalc();
    }

    function bindRow(tr) {
      var sel = tr.querySelector('.account-select');
      var title = tr.querySelector('.title-view');
      var amt = tr.querySelector('.amount-input');
      var prev = tr.querySelector('.prev-bal');
      var rm = tr.querySelector('.btn-remove');

      sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        title.value = opt.getAttribute('data-title') || '';
        var bal = parseFloat(opt.getAttribute('data-balance') || '0') || 0;
        prev.textContent = money(bal);
        if (sel.value) {
          Apex.api('/api/accounts.php?id=' + encodeURIComponent(sel.value)).then(function (d) {
            if (d.success) prev.textContent = money(d.balance);
          });
        }
        recalc();
      });
      amt.addEventListener('input', recalc);
      rm.addEventListener('click', function () {
        if (Apex.qsa('.line-row', els.body).length <= 2) {
          Apex.toast('At least 2 rows required (party + cash).', 'error');
          return;
        }
        tr.remove();
        renumber();
        recalc();
      });
    }

    function collectLines() {
      var lines = [];
      Apex.qsa('.line-row', els.body).forEach(function (tr) {
        lines.push({
          subsidiary_id: parseInt(tr.querySelector('.account-select').value, 10) || 0,
          narration: tr.querySelector('.narr-input').value.trim(),
          amount: parseAmt(tr.querySelector('.amount-input').value),
        });
      });
      return lines;
    }

    function recalc() {
      var lines = collectLines();
      var total = 0;
      var valid = 0;
      lines.forEach(function (l) {
        if (l.amount > 0) total += l.amount;
        if (l.subsidiary_id && l.amount > 0) valid += 1;
      });
      // For 2-side balance UI: show half if exactly 2 equal? Just show sum of line amounts / 2 conceptually
      // Display sum of amounts on "other" side is confusing; show total of filled amounts on first side
      // Better: show sum of all amounts / 2 when even pair — actually show max of partial
      var filledAmts = lines.filter(function (l) { return l.subsidiary_id && l.amount > 0; });
      var displayTotal = 0;
      if (filledAmts.length >= 2) {
        // show average of two sides if 2 lines with amounts
        displayTotal = filledAmts[0].amount;
        // if more than 2, sum non-last vs last? For simplicity sum of first n-1 if last is cash
        if (filledAmts.length === 2) {
          displayTotal = filledAmts[0].amount; // party amount
        } else {
          displayTotal = filledAmts.reduce(function (s, l) { return s + l.amount; }, 0) / 2;
        }
      } else if (filledAmts.length === 1) {
        displayTotal = filledAmts[0].amount;
      }
      if (els.total) {
        if (filledAmts.length === 2) {
          els.total.textContent = money(filledAmts[0].amount);
        } else {
          els.total.textContent = money(displayTotal);
        }
      }

      var ok = false;
      if (valid >= 2) {
        if (filledAmts.length === 2) {
          ok = Math.abs(filledAmts[0].amount - filledAmts[1].amount) < 0.009 && filledAmts[0].amount > 0;
        } else {
          // multi-line: allow save if at least 2 valid (server validates cash vs other totals)
          ok = filledAmts.every(function (l) { return l.amount > 0; }) && filledAmts.length >= 2;
        }
      }
      if (els.save) els.save.disabled = !ok;
    }

    async function refreshNumbers() {
      if (els.ref) els.ref.value = '…';
      if (els.seq) els.seq.value = '…';
      try {
        var d = await Apex.api(
          '/api/next_voucher.php?type=' +
            encodeURIComponent(type) +
            '&date=' +
            encodeURIComponent(els.date.value)
        );
        if (d.success) {
          if (els.ref) els.ref.value = d.voucher_no != null ? d.voucher_no : d.voucher_ref;
          if (els.seq) els.seq.value = d.sequence_no;
        } else {
          if (els.ref) els.ref.value = '—';
          if (els.seq) els.seq.value = '—';
        }
      } catch (e) {
        if (els.ref) els.ref.value = '—';
        if (els.seq) els.seq.value = '—';
      }
    }

    async function loadAccounts() {
      var d = await Apex.api('/api/accounts.php');
      if (!d.success) throw new Error(d.message || 'Failed to load accounts');
      accounts = d.accounts || [];
    }

    async function onSubmit(e) {
      e.preventDefault();
      var lines = collectLines().filter(function (l) {
        return l.subsidiary_id && l.amount > 0;
      });
      if (lines.length < 2) {
        Apex.toast('Need at least 2 lines (party + cash).', 'error');
        return;
      }
      if (lines.length === 2 && Math.abs(lines[0].amount - lines[1].amount) > 0.009) {
        Apex.toast('Both row amounts must match.', 'error');
        return;
      }

      els.save.disabled = true;
      els.save.textContent = 'Saving…';
      try {
        var d = await Apex.api('/api/save_voucher.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            voucher_type: type,
            voucher_date: els.date.value,
            lines: lines,
          }),
        });
        if (!d.success) throw new Error(d.message || 'Save failed');
        Apex.toast('Saved #' + d.data.voucher_no + ' · Seq #' + d.data.sequence_no, 'success');
        els.body.innerHTML = '';
        addRow();
        addRow();
        await refreshNumbers();
        await loadAccounts();
        Apex.qsa('.account-select', els.body).forEach(function (sel) {
          sel.innerHTML = optionsHtml(sel.value);
        });
        recalc();
      } catch (err) {
        Apex.toast(err.message || 'Error', 'error');
        recalc();
      } finally {
        els.save.textContent = type === 'CRV' ? 'Save Receipt' : 'Save Payment';
      }
    }

    (async function init() {
      try {
        await loadAccounts();
      } catch (e) {
        Apex.toast(e.message || 'Could not load accounts', 'error');
      }
      addRow();
      addRow();
      await refreshNumbers();
      els.date.addEventListener('change', refreshNumbers);
      if (els.add) {
        els.add.addEventListener('click', function (ev) {
          ev.preventDefault();
          addRow();
        });
      }
      form.addEventListener('submit', onSubmit);
      recalc();
    })();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
