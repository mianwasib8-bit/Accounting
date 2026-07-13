<?php
/**
 * Subsidiary Head — ARC-style split form + table
 * Code + title only (opening balance on Chart of Accounts)
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\MainHead;
use App\Models\SubsidiaryHead;

$pageTitle = 'Subsidiaries';
$activePage = 'subsidiary_head';

$message = '';
$error = '';
$mains = MainHead::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $row = SubsidiaryHead::create([
            'sub_head_id'  => (int)($_POST['sub_head_id'] ?? 0),
            'title'        => $_POST['title'] ?? '',
            'account_type' => $_POST['account_type'] ?? 'general',
        ]);
        $message = "Saved {$row['full_code']} — {$row['title']}. Set opening balance in Chart of Accounts.";
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = SubsidiaryHead::all();

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<?php if ($message): ?><div class="alert alert-success mb-4"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error mb-4"><?= e($error) ?></div><?php endif; ?>

<div class="split-2">
  <!-- Left: Add form -->
  <div class="card accent-left card-pad">
    <p class="card-kicker">Accounting Masters</p>
    <h2 class="card-title">Add Subsidiary</h2>

    <form method="POST" class="form-grid" id="subsi-form">
      <div class="field">
        <label for="main_head_id">Main Head</label>
        <select class="select" id="main_head_id" required>
          <option value="">— Select main head —</option>
          <?php foreach ($mains as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= e($m['code'] . ' — ' . $m['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="sub_head_id">Sub Head</label>
        <select class="select" id="sub_head_id" name="sub_head_id" required>
          <option value="">— Select main head first —</option>
        </select>
      </div>

      <div class="form-grid cols-2">
        <div class="field">
          <label>Sub Code</label>
          <input class="input input-mono" id="sub_code_view" type="text" value="—" readonly disabled />
        </div>
        <div class="field">
          <label>Subsidiary Code <span class="hint">(auto · 5 digits · full 9)</span></label>
          <input class="input input-mono" id="code_preview" type="text" value="—" readonly disabled />
        </div>
      </div>

      <div class="field">
        <label for="title">Subsidiary Title</label>
        <input class="input" type="text" id="title" name="title" required maxlength="150" placeholder="e.g. Cash in Hand" />
      </div>

      <div class="field">
        <label for="account_type">Account Type</label>
        <select class="select" id="account_type" name="account_type">
          <option value="general">General</option>
          <option value="cash">Cash</option>
          <option value="bank">Bank</option>
          <option value="party">Party</option>
        </select>
      </div>

      <button type="submit" class="btn-save-dark">Save Subsidiary</button>
    </form>
  </div>

  <!-- Right: Table -->
  <div class="card accent-left">
    <div class="card-pad" style="border-bottom:1px solid var(--line)">
      <h2 class="card-title" style="margin:0;font-size:20px">Subsidiary Table</h2>
    </div>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Sub Code</th>
            <th>Subsidiary Code</th>
            <th>Sub Head</th>
            <th>Title</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="4" class="empty-state">No subsidiary heads yet.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td class="num"><?= e($r['sub_full_code']) ?></td>
            <td class="num" style="color:var(--brand);font-weight:700"><?= e($r['full_code']) ?></td>
            <td><?= e($r['sub_title']) ?></td>
            <td><strong><?= e($r['title']) ?></strong></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  var mainSel = document.getElementById('main_head_id');
  var subSel = document.getElementById('sub_head_id');
  var codePrev = document.getElementById('code_preview');
  var subCode = document.getElementById('sub_code_view');

  mainSel.addEventListener('change', function () {
    var id = this.value;
    subSel.innerHTML = '<option value="">Loading…</option>';
    codePrev.value = '—';
    subCode.value = '—';
    if (!id) {
      subSel.innerHTML = '<option value="">— Select main head first —</option>';
      return;
    }
    Apex.api('/api/sub_heads.php?main_head_id=' + encodeURIComponent(id)).then(function (d) {
      if (!d.success) {
        subSel.innerHTML = '<option value="">Error</option>';
        return;
      }
      var html = '<option value="">— Select —</option>';
      (d.items || []).forEach(function (s) {
        html += '<option value="' + s.id + '" data-full="' + s.full_code + '">' + s.full_code + ' — ' + s.title + '</option>';
      });
      subSel.innerHTML = html;
    });
  });

  subSel.addEventListener('change', function () {
    var id = this.value;
    var opt = this.options[this.selectedIndex];
    subCode.value = opt.getAttribute('data-full') || '—';
    codePrev.value = '—';
    if (!id) return;
    Apex.api('/api/next_code.php?type=subsidiary&sub_head_id=' + encodeURIComponent(id)).then(function (d) {
      if (d.success) codePrev.value = d.full_code;
    });
  });
})();
</script>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
