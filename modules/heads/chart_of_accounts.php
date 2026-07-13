<?php
/**
 * Chart of Accounts — ARC-style opening setup form + list
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\MainHead;
use App\Models\SubsidiaryHead;

$pageTitle = 'Chart of Accounts';
$activePage = 'coa';

$tree = SubsidiaryHead::chartTree();
$mains = MainHead::all();
$allSubsidiaries = SubsidiaryHead::all();
$today = date('Y-m-d');

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<div class="split-2">
  <!-- Left: Opening setup -->
  <div class="card accent-left card-pad">
    <p class="card-kicker">Opening Setup</p>
    <h2 class="card-title">Chart of Accounts</h2>

    <form id="ob-setup-form" class="form-grid">
      <div class="field">
        <label for="coa_main">Main Head</label>
        <select class="select" id="coa_main">
          <option value="">— Select main head —</option>
          <?php foreach ($mains as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= e($m['code'] . ' — ' . $m['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="coa_sub">Sub Head</label>
        <select class="select" id="coa_sub">
          <option value="">— Select main first —</option>
        </select>
      </div>

      <div class="field">
        <label for="coa_subsi">Subsidiary</label>
        <select class="select" id="coa_subsi" required>
          <option value="">— Select sub head first —</option>
        </select>
      </div>

      <div class="form-grid cols-2">
        <div class="field">
          <label for="coa_code">Subsidiary Code</label>
          <input class="input input-mono" id="coa_code" type="text" readonly disabled value="—" />
        </div>
        <div class="field">
          <label for="coa_nature">Default Nature</label>
          <select class="select" id="coa_nature">
            <option value="Dr">Dr</option>
            <option value="Cr">Cr</option>
          </select>
        </div>
      </div>

      <div class="form-grid cols-2">
        <div class="field">
          <label for="coa_ob">Opening Balance</label>
          <input class="input" type="number" step="0.01" id="coa_ob" value="0.00" />
        </div>
        <div class="field">
          <label for="coa_date">Opening Date</label>
          <input class="input" type="date" id="coa_date" value="<?= e($today) ?>" />
        </div>
      </div>

      <div class="field">
        <label for="coa_type">Account Type</label>
        <select class="select" id="coa_type">
          <option value="general">General</option>
          <option value="cash">Cash</option>
          <option value="bank">Bank</option>
          <option value="party">Party</option>
        </select>
      </div>

      <button type="submit" class="btn-save-dark">Save Opening Setup</button>
    </form>
  </div>

  <!-- Right: balances list -->
  <div class="card accent-left">
    <div class="card-pad" style="border-bottom:1px solid var(--line)">
      <h2 class="card-title" style="margin:0;font-size:20px">Accounts &amp; Balances</h2>
    </div>
    <div class="table-wrap">
      <table class="data" id="coa-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Title</th>
            <th class="text-right">Balance</th>
            <th>Nature</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$allSubsidiaries): ?>
          <tr><td colspan="4" class="empty-state">No accounts yet.</td></tr>
        <?php else: foreach ($allSubsidiaries as $r):
          $bal = \App\Services\LedgerService::previousBalance((int)$r['id']);
        ?>
          <tr data-id="<?= (int)$r['id'] ?>">
            <td class="num" style="color:var(--brand);font-weight:700"><?= e($r['full_code']) ?></td>
            <td><strong><?= e($r['title']) ?></strong></td>
            <td class="text-right num bal-cell"><?= number_format($bal, 2) ?></td>
            <td><span class="pill nature-cell <?= $r['nature'] === 'Dr' ? 'pill-sky' : 'pill-amber' ?>"><?= e($r['nature']) ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Tree view (optional) -->
<div class="mt-4">
  <div class="section-head">
    <div>
      <h2 style="font-size:18px">Hierarchy View</h2>
      <p>Main → Sub → Subsidiary (optional)</p>
    </div>
    <button type="button" id="btn-toggle-hierarchy" class="btn btn-secondary btn-sm">Show Hierarchy</button>
  </div>
  <div id="coa-hierarchy-panel" class="hidden">
  <?php if (!$tree): ?>
    <div class="card card-pad empty-state">No accounts configured yet.</div>
  <?php else: foreach ($tree as $main): ?>
    <div class="coa-main">
      <div class="coa-main-head">
        <div class="coa-code"><?= e($main['code']) ?></div>
        <div>
          <div style="font-weight:700;font-size:15px"><?= e($main['title']) ?></div>
          <div style="font-size:11px;opacity:.7">Main Head</div>
        </div>
      </div>
      <?php if (!$main['subs']): ?>
        <div class="coa-sub" style="color:var(--muted);font-size:13px">No sub heads.</div>
      <?php else: foreach ($main['subs'] as $sub): ?>
        <div class="coa-sub">
          <div class="coa-sub-title">
            <span class="pill pill-sky"><?= e($sub['code']) ?></span>
            <span><?= e($sub['title']) ?></span>
          </div>
          <?php if (!$sub['subsidiaries']): ?>
            <div style="font-size:13px;color:var(--muted);padding-left:12px">No subsidiaries.</div>
          <?php else: foreach ($sub['subsidiaries'] as $leaf): ?>
            <div class="coa-leaf">
              <span class="num" style="color:var(--brand);font-weight:700"><?= e($leaf['full_code']) ?></span>
              <span style="font-weight:600"><?= e($leaf['title']) ?></span>
              <span class="num text-right" style="font-weight:700"><?= number_format((float)$leaf['balance'], 2) ?></span>
              <span class="pill <?= $leaf['nature'] === 'Dr' ? 'pill-sky' : 'pill-amber' ?>"><?= e($leaf['nature']) ?></span>
              <span></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  <?php endforeach; endif; ?>
  </div>
</div>

<script>
(function () {
  var mainSel = document.getElementById('coa_main');
  var subSel = document.getElementById('coa_sub');
  var subsiSel = document.getElementById('coa_subsi');
  var codeEl = document.getElementById('coa_code');
  var natureEl = document.getElementById('coa_nature');
  var obEl = document.getElementById('coa_ob');
  var typeEl = document.getElementById('coa_type');
  var form = document.getElementById('ob-setup-form');

  // Cache subsidiaries from table for quick fill
  var accounts = {};
  document.querySelectorAll('#coa-table tbody tr[data-id]').forEach(function (tr) {
    accounts[tr.getAttribute('data-id')] = {
      code: tr.cells[0].textContent.trim(),
      title: tr.cells[1].textContent.trim(),
      nature: tr.querySelector('.nature-cell') ? tr.querySelector('.nature-cell').textContent.trim() : 'Dr'
    };
  });

  mainSel.addEventListener('change', function () {
    var id = this.value;
    subSel.innerHTML = '<option value="">Loading…</option>';
    subsiSel.innerHTML = '<option value="">— Select sub head first —</option>';
    codeEl.value = '—';
    if (!id) {
      subSel.innerHTML = '<option value="">— Select main first —</option>';
      return;
    }
    Apex.api('/api/sub_heads.php?main_head_id=' + encodeURIComponent(id)).then(function (d) {
      var html = '<option value="">— Select —</option>';
      (d.items || []).forEach(function (s) {
        html += '<option value="' + s.id + '">' + s.full_code + ' — ' + s.title + '</option>';
      });
      subSel.innerHTML = html;
    });
  });

  subSel.addEventListener('change', function () {
    var id = this.value;
    subsiSel.innerHTML = '<option value="">Loading…</option>';
    codeEl.value = '—';
    if (!id) {
      subsiSel.innerHTML = '<option value="">— Select sub head first —</option>';
      return;
    }
    Apex.api('/api/accounts.php?sub_head_id=' + encodeURIComponent(id)).then(function (d) {
      var list = d.accounts || [];
      var html = '<option value="">— Select subsidiary —</option>';
      list.forEach(function (a) {
        html += '<option value="' + a.id + '" data-code="' + a.code + '" data-nature="' + a.nature + '" data-type="' + (a.type || 'general') + '" data-balance="' + a.balance + '">' + a.label + '</option>';
      });
      if (!list.length) {
        html = '<option value="">No subsidiaries under this sub head</option>';
      }
      subsiSel.innerHTML = html;
    });
  });

  subsiSel.addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    if (!this.value) {
      codeEl.value = '—';
      return;
    }
    codeEl.value = opt.getAttribute('data-code') || '—';
    natureEl.value = opt.getAttribute('data-nature') || 'Dr';
    typeEl.value = opt.getAttribute('data-type') || 'general';
    var bal = parseFloat(opt.getAttribute('data-balance') || '0') || 0;
    // Opening balance field shows current OB ideally; balance is live — keep user editable
    if (!obEl.value || obEl.value === '0' || obEl.value === '0.00') {
      // leave as is or fetch
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var id = parseInt(subsiSel.value, 10);
    if (!id) {
      Apex.toast('Select a subsidiary account.', 'error');
      return;
    }
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Saving…';
    Apex.api('/api/update_opening.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: id,
        opening_balance: parseFloat(obEl.value) || 0,
        nature: natureEl.value,
        account_type: typeEl.value
      })
    }).then(function (d) {
      btn.disabled = false;
      btn.textContent = 'Save Opening Setup';
      if (!d.success) {
        Apex.toast(d.message || 'Save failed', 'error');
        return;
      }
      Apex.toast('Opening setup saved', 'success');
      // Update table row
      var tr = document.querySelector('#coa-table tr[data-id="' + id + '"]');
      if (tr) {
        var balCell = tr.querySelector('.bal-cell');
        if (balCell) balCell.textContent = Apex.money(d.data.balance);
        var nat = tr.querySelector('.nature-cell');
        if (nat) {
          nat.textContent = d.data.nature;
          nat.className = 'pill nature-cell ' + (d.data.nature === 'Dr' ? 'pill-sky' : 'pill-amber');
        }
      }
      // Soft reload for tree accuracy
      setTimeout(function () { window.location.reload(); }, 600);
    }).catch(function (err) {
      btn.disabled = false;
      btn.textContent = 'Save Opening Setup';
      Apex.toast(err.message || 'Error', 'error');
    });
  });
})();
</script>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
