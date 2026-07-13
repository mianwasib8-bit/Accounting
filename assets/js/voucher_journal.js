/**
 * Journal Voucher — Debit / Credit double-entry
 * Min 2 rows · Total Dr === Total Cr · shared sequence with CRV/CPV
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
    if (!typeEl || typeEl.value !== 'JV') return;

    var els = {
      ref: document.getElementById('voucher_ref'),
      seq: document.getElementById('sequence_no'),
      date: document.getElementById('voucher_date'),
      body: document.getElementById('lines-body'),
      totalDr: document.getElementById('total-debit'),
      totalCr: document.getElementById('total-credit'),
      bal: document.getElementById('balance-status'),
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
        h +=
          '<option value="' +
          a.id +
          '" data-title="' +
          esc(a.title) +
          '" data-balance="' +
          a.balance +
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
        '<td><input class="input input-sm debit-input" type="number" min="0" step="0.01" placeholder="0.00" style="text-align:right" /></td>' +
        '<td><input class="input input-sm credit-input" type="number" min="0" step="0.01" placeholder="0.00" style="text-align:right" /></td>' +
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
      var dr = tr.querySelector('.debit-input');
      var cr = tr.querySelector('.credit-input');
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

      function onAmt(e) {
        if (e.target === dr && parseAmt(dr.value) > 0) cr.value = '';
        if (e.target === cr && parseAmt(cr.value) > 0) dr.value = '';
        recalc();
      }
      dr.addEventListener('input', onAmt);
      cr.addEventListener('input', onAmt);

      rm.addEventListener('click', function () {
        if (Apex.qsa('.line-row', els.body).length <= 2) {
          Apex.toast('At least 2 rows required.', 'error');
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
          debit_amount: parseAmt(tr.querySelector('.debit-input').value),
          credit_amount: parseAmt(tr.querySelector('.credit-input').value),
        });
      });
      return lines;
    }

    function recalc() {
      var lines = collectLines();
      var dr = 0;
      var cr = 0;
      var filled = 0;
      lines.forEach(function (l) {
        dr += l.debit_amount;
        cr += l.credit_amount;
        if (l.subsidiary_id && (l.debit_amount > 0 || l.credit_amount > 0)) filled += 1;
      });

      if (els.totalDr) els.totalDr.textContent = money(dr);
      if (els.totalCr) els.totalCr.textContent = money(cr);

      var balanced = Math.abs(dr - cr) < 0.009 && dr > 0;
      if (els.bal) {
        if (dr === 0 && cr === 0) {
          els.bal.textContent = 'Enter amounts';
          els.bal.className = '';
          els.bal.style.color = 'var(--muted)';
        } else if (balanced) {
          els.bal.textContent = '✓ Balanced';
          els.bal.className = 'balance-ok';
          els.bal.style.color = '';
        } else {
          els.bal.textContent = 'Out of balance by ' + money(Math.abs(dr - cr));
          els.bal.className = 'balance-bad';
          els.bal.style.color = '';
        }
      }
      if (els.save) els.save.disabled = !(balanced && filled >= 2);
    }

    async function refreshNumbers() {
      if (els.ref) els.ref.value = '…';
      if (els.seq) els.seq.value = '…';
      try {
        var d = await Apex.api(
          '/api/next_voucher.php?type=JV&date=' + encodeURIComponent(els.date.value)
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
      var lines = collectLines();
      var dr = 0;
      var cr = 0;
      lines.forEach(function (l) {
        dr += l.debit_amount;
        cr += l.credit_amount;
      });
      if (Math.abs(dr - cr) > 0.009 || dr <= 0) {
        Apex.toast('Total Debit must equal Total Credit.', 'error');
        return;
      }
      var filled = lines.filter(function (l) {
        return l.subsidiary_id && (l.debit_amount > 0 || l.credit_amount > 0);
      });
      if (filled.length < 2) {
        Apex.toast('Need at least 2 lines with amounts.', 'error');
        return;
      }

      els.save.disabled = true;
      els.save.textContent = 'Saving…';
      try {
        var d = await Apex.api('/api/save_voucher.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            voucher_type: 'JV',
            voucher_date: els.date.value,
            lines: lines,
          }),
        });
        if (!d.success) throw new Error(d.message || 'Save failed');
        Apex.toast('Saved JV #' + d.data.voucher_no + ' · Seq #' + d.data.sequence_no, 'success');
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
        els.save.textContent = 'Save Journal';
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
