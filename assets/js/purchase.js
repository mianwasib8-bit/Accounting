/**
 * Purchase Form
 * Invoice auto · party balance · item lines with double discount · bill expense · net
 */
(function () {
  'use strict';

  function boot() {
    if (typeof window.Apex === 'undefined') {
      setTimeout(boot, 40);
      return;
    }
    var form = document.getElementById('purchase-form');
    if (!form) return;

    var bootData = window.PURCHASE_BOOT || { items: [], partyBalances: {} };
    var items = bootData.items || [];
    var partyBalances = bootData.partyBalances || {};

    var els = {
      invoice: document.getElementById('invoice_no'),
      date: document.getElementById('purchase_date'),
      bill: document.getElementById('bill_no'),
      mode: document.getElementById('pay_mode'),
      cashField: document.getElementById('cash-field'),
      cash: document.getElementById('cash_account_id'),
      party: document.getElementById('party_id'),
      partyCode: document.getElementById('party_code'),
      partyBal: document.getElementById('party_balance'),
      company: document.getElementById('company_name'),
      body: document.getElementById('lines-body'),
      subtotal: document.getElementById('subtotal'),
      expense: document.getElementById('bill_expense'),
      net: document.getElementById('net_amount'),
      save: document.getElementById('btn-save'),
      add: document.getElementById('btn-add-row'),
      clear: document.getElementById('btn-clear'),
    };

    function money(n) {
      return Apex.money(n);
    }
    function num(v) {
      var n = parseFloat(String(v).replace(/,/g, ''));
      return isNaN(n) || n < 0 ? 0 : n;
    }
    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function itemOptions(selected) {
      var h = '<option value="">— Select item —</option>';
      items.forEach(function (it) {
        var sel = String(it.id) === String(selected) ? ' selected' : '';
        h +=
          '<option value="' +
          it.id +
          '" data-code="' +
          esc(it.code) +
          '" data-name="' +
          esc(it.name) +
          '" data-packing="' +
          esc(it.packing || '') +
          '" data-rate="' +
          it.purchase_rate +
          '"' +
          sel +
          '>' +
          esc(it.name) +
          '</option>';
      });
      return h;
    }

    function renumber() {
      Apex.qsa('.pline', els.body).forEach(function (tr, i) {
        var c = tr.querySelector('.line-seq');
        if (c) c.textContent = String(i + 1);
      });
    }

    function calcRow(tr) {
      var qty = num(tr.querySelector('.qty-input').value);
      var rate = num(tr.querySelector('.rate-input').value);
      var d1 = num(tr.querySelector('.d1-input').value);
      var d2 = num(tr.querySelector('.d2-input').value);
      if (d1 > 100) d1 = 100;
      if (d2 > 100) d2 = 100;

      var gross = Math.round(qty * rate * 100) / 100;
      var d1Amt = Math.round(gross * d1) / 100;
      var after1 = Math.round((gross - d1Amt) * 100) / 100;
      var d2Amt = Math.round(after1 * d2) / 100;
      var net = Math.round((after1 - d2Amt) * 100) / 100;

      tr.querySelector('.gross-view').textContent = money(gross);
      tr.querySelector('.d1amt-view').textContent = money(d1Amt);
      tr.querySelector('.after1-view').textContent = money(after1);
      tr.querySelector('.net-view').textContent = money(net);
      return net;
    }

    function recalc() {
      var total = 0;
      var valid = 0;
      Apex.qsa('.pline', els.body).forEach(function (tr) {
        var net = calcRow(tr);
        var itemId = parseInt(tr.querySelector('.item-select').value, 10) || 0;
        var qty = num(tr.querySelector('.qty-input').value);
        if (itemId && qty > 0) {
          total += net;
          valid += 1;
        }
      });
      total = Math.round(total * 100) / 100;
      var exp = num(els.expense.value);
      var finalNet = Math.round((total + exp) * 100) / 100;
      els.subtotal.textContent = money(total);
      els.net.textContent = money(finalNet);

      var partyOk = !!els.party.value;
      var cashOk = els.mode.value !== 'Cash' || !!els.cash.value;
      els.save.disabled = !(valid >= 1 && finalNet > 0 && partyOk && cashOk);
    }

    function addRow() {
      var tr = document.createElement('tr');
      tr.className = 'pline';
      tr.innerHTML =
        '<td class="line-seq num" style="font-weight:700;color:var(--muted)">1</td>' +
        '<td><select class="select input-sm item-select">' +
        itemOptions('') +
        '</select></td>' +
        '<td><input class="input input-sm title-view" type="text" readonly tabindex="-1" placeholder="—" /></td>' +
        '<td><input class="input input-sm pack-view" type="text" readonly tabindex="-1" placeholder="—" /></td>' +
        '<td><input class="input input-sm batch-input" type="text" maxlength="50" placeholder="Batch" /></td>' +
        '<td><input class="input input-sm qty-input" type="number" min="0" step="0.001" placeholder="0" style="text-align:right" /></td>' +
        '<td><input class="input input-sm rate-input" type="number" min="0" step="0.01" placeholder="0.00" style="text-align:right" /></td>' +
        '<td class="text-right num gross-view">0.00</td>' +
        '<td><input class="input input-sm d1-input" type="number" min="0" max="100" step="0.01" value="0" style="text-align:right" /></td>' +
        '<td class="text-right num d1amt-view">0.00</td>' +
        '<td class="text-right num after1-view">0.00</td>' +
        '<td><input class="input input-sm d2-input" type="number" min="0" max="100" step="0.01" value="0" style="text-align:right" /></td>' +
        '<td class="text-right num net-view" style="font-weight:700">0.00</td>' +
        '<td class="text-center"><button type="button" class="icon-btn btn-remove" style="width:30px;height:30px;color:var(--muted)" title="Remove">' +
        '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
        '</button></td>';
      els.body.appendChild(tr);
      bindRow(tr);
      renumber();
      recalc();
    }

    function bindRow(tr) {
      var sel = tr.querySelector('.item-select');
      var title = tr.querySelector('.title-view');
      var pack = tr.querySelector('.pack-view');
      var rate = tr.querySelector('.rate-input');
      var rm = tr.querySelector('.btn-remove');

      sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        title.value = opt.getAttribute('data-name') || '';
        pack.value = opt.getAttribute('data-packing') || '';
        var r = parseFloat(opt.getAttribute('data-rate') || '0') || 0;
        if (r > 0) rate.value = r;
        recalc();
      });

      ['qty-input', 'rate-input', 'd1-input', 'd2-input'].forEach(function (cls) {
        tr.querySelector('.' + cls).addEventListener('input', recalc);
      });

      rm.addEventListener('click', function () {
        if (Apex.qsa('.pline', els.body).length <= 1) {
          Apex.toast('At least one line required.', 'error');
          return;
        }
        tr.remove();
        renumber();
        recalc();
      });
    }

    function collectLines() {
      var lines = [];
      Apex.qsa('.pline', els.body).forEach(function (tr) {
        lines.push({
          item_id: parseInt(tr.querySelector('.item-select').value, 10) || 0,
          batch_no: tr.querySelector('.batch-input').value.trim(),
          qty: num(tr.querySelector('.qty-input').value),
          rate: num(tr.querySelector('.rate-input').value),
          disc1_pct: num(tr.querySelector('.d1-input').value),
          disc2_pct: num(tr.querySelector('.d2-input').value),
        });
      });
      return lines;
    }

    async function refreshInvoice() {
      els.invoice.value = '…';
      try {
        var d = await Apex.api(
          '/api/next_purchase.php?date=' + encodeURIComponent(els.date.value)
        );
        if (d.success) els.invoice.value = d.invoice_no;
        else els.invoice.value = '—';
      } catch (e) {
        els.invoice.value = '—';
      }
    }

    function onPartyChange() {
      var id = els.party.value;
      var opt = els.party.options[els.party.selectedIndex];
      els.partyCode.value = id ? opt.getAttribute('data-code') || '—' : '—';
      var bal = id ? partyBalances[id] || partyBalances[String(id)] || 0 : 0;
      els.partyBal.value = money(bal);
      // live refresh balance
      if (id) {
        Apex.api('/api/accounts.php?id=' + encodeURIComponent(id)).then(function (d) {
          if (d.success) {
            els.partyBal.value = money(d.balance);
            partyBalances[id] = d.balance;
          }
        });
      }
      recalc();
    }

    function onModeChange() {
      var cash = els.mode.value === 'Cash';
      els.cashField.style.display = cash ? '' : 'none';
      recalc();
    }

    function clearForm() {
      els.bill.value = '';
      els.company.value = '';
      els.expense.value = '0.00';
      els.mode.value = 'Credit';
      onModeChange();
      els.party.value = '';
      onPartyChange();
      els.body.innerHTML = '';
      addRow();
      refreshInvoice();
      recalc();
    }

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var lines = collectLines().filter(function (l) {
        return l.item_id && l.qty > 0;
      });
      if (!lines.length) {
        Apex.toast('Add at least one item line.', 'error');
        return;
      }
      if (!els.party.value) {
        Apex.toast('Select party.', 'error');
        return;
      }
      if (els.mode.value === 'Cash' && !els.cash.value) {
        Apex.toast('Select cash / bank account.', 'error');
        return;
      }

      els.save.disabled = true;
      els.save.textContent = 'Saving…';
      try {
        var d = await Apex.api('/api/save_purchase.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            purchase_date: els.date.value,
            bill_no: els.bill.value.trim(),
            pay_mode: els.mode.value,
            party_id: parseInt(els.party.value, 10),
            company_name: els.company.value.trim(),
            cash_account_id: parseInt(els.cash.value, 10) || 0,
            bill_expense: num(els.expense.value),
            lines: lines,
          }),
        });
        if (!d.success) throw new Error(d.message || 'Save failed');
        Apex.toast(
          'Purchase #' + d.data.invoice_no + ' saved · Net ' + money(d.data.net_amount),
          'success'
        );
        // refresh party balance cache
        if (els.party.value) {
          Apex.api('/api/accounts.php?id=' + encodeURIComponent(els.party.value)).then(function (b) {
            if (b.success) partyBalances[els.party.value] = b.balance;
          });
        }
        clearForm();
      } catch (err) {
        Apex.toast(err.message || 'Error', 'error');
        recalc();
      } finally {
        els.save.textContent = 'Save';
      }
    });

    els.date.addEventListener('change', refreshInvoice);
    els.party.addEventListener('change', onPartyChange);
    els.mode.addEventListener('change', onModeChange);
    els.expense.addEventListener('input', recalc);
    if (els.cash) els.cash.addEventListener('change', recalc);
    if (els.add) {
      els.add.addEventListener('click', function (ev) {
        ev.preventDefault();
        addRow();
      });
    }
    if (els.clear) {
      els.clear.addEventListener('click', function () {
        clearForm();
      });
    }

    addRow();
    onModeChange();
    refreshInvoice();
    recalc();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
