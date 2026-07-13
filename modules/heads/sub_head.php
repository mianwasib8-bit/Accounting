<?php
/**
 * Sub Head Module
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\MainHead;
use App\Models\SubHead;
use App\Services\CodeGenerator;

$pageTitle = 'Sub Head';
$activePage = 'sub_head';

$message = '';
$error = '';
$mains = MainHead::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $row = SubHead::create((int)($_POST['main_head_id'] ?? 0), $_POST['title'] ?? '');
        $message = "Saved Sub Head {$row['full_code']} — {$row['title']}";
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = SubHead::all();
$selectedMain = (int)($_POST['main_head_id'] ?? ($mains[0]['id'] ?? 0));
$preview = ['code' => '—', 'full_code' => '—', 'main_code' => '—'];
if ($selectedMain > 0) {
    try {
        $preview = CodeGenerator::nextSubHeadCode($selectedMain);
    } catch (Throwable $e) {
        // ignore preview errors
    }
}

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<?php if ($message): ?><div class="alert alert-success mb-4"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error mb-4"><?= e($error) ?></div><?php endif; ?>

<div class="split-2">
  <div class="card accent-left card-pad">
    <p class="card-kicker">Accounting Masters</p>
    <h2 class="card-title">Add Sub Head</h2>
    <form method="POST" class="form-grid" id="sub-form">
      <div class="field">
        <label for="main_head_id">Select Main Head</label>
        <select class="select" id="main_head_id" name="main_head_id" required>
          <option value="">— Select —</option>
          <?php foreach ($mains as $m): ?>
            <option value="<?= (int)$m['id'] ?>" data-code="<?= e($m['code']) ?>" <?= $selectedMain === (int)$m['id'] ? 'selected' : '' ?>>
              <?= e($m['code'] . ' — ' . $m['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grid cols-2">
        <div class="field">
          <label>Main Head Code</label>
          <input class="input input-mono" id="main_code_preview" type="text" value="<?= e($preview['main_code']) ?>" readonly disabled />
        </div>
        <div class="field">
          <label>Sub Head Code <span class="hint">(auto · 2 digits · full 4)</span></label>
          <input class="input input-mono" id="sub_code_preview" type="text" value="<?= e($preview['full_code']) ?>" readonly disabled />
        </div>
      </div>
      <div class="field">
        <label for="title">Sub Head Title</label>
        <input class="input" type="text" id="title" name="title" required maxlength="150" placeholder="e.g. Current Assets" />
      </div>
      <button type="submit" class="btn-save-dark">Save Sub Head</button>
    </form>
  </div>

  <div class="card accent-left">
    <div class="card-pad" style="border-bottom:1px solid var(--line)">
      <h2 class="card-title" style="margin:0;font-size:20px">Sub Head Table</h2>
    </div>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Main Head</th><th>Code</th><th>Title</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="3" class="empty-state">No sub heads yet.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['main_code'] . ' · ' . $r['main_title']) ?></td>
            <td class="num" style="color:var(--brand);font-weight:700"><?= e($r['full_code']) ?></td>
            <td><strong><?= e($r['title']) ?></strong></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.getElementById('main_head_id').addEventListener('change', function () {
  // Reload page with selection to refresh auto code preview
  // lightweight: fetch next code via API
  var id = this.value;
  var opt = this.options[this.selectedIndex];
  document.getElementById('main_code_preview').value = opt.getAttribute('data-code') || '—';
  if (!id) {
    document.getElementById('sub_code_preview').value = '—';
    return;
  }
  Apex.api('/api/next_code.php?type=sub&main_head_id=' + encodeURIComponent(id))
    .then(function (d) {
      if (d.success) document.getElementById('sub_code_preview').value = d.full_code;
    });
});
</script>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
