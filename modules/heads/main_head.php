<?php
/**
 * Main Head Module — auto code, manual title
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\MainHead;
use App\Services\CodeGenerator;

$pageTitle = 'Main Head';
$activePage = 'main_head';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $row = MainHead::create($_POST['title'] ?? '');
        $message = "Saved Main Head {$row['code']} — {$row['title']}";
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$nextCode = CodeGenerator::nextMainHeadCode();
$rows = MainHead::all();

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<?php if ($message): ?><div class="alert alert-success mb-4"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error mb-4"><?= e($error) ?></div><?php endif; ?>

<div class="split-2">
  <div class="card accent-left card-pad">
    <p class="card-kicker">Accounting Masters</p>
    <h2 class="card-title">Add Main Head</h2>
    <form method="POST" class="form-grid">
      <div class="field">
        <label>Main Head Code <span class="hint">(auto · 2 digits)</span></label>
        <input class="input input-mono" type="text" value="<?= e($nextCode) ?>" readonly disabled />
      </div>
      <div class="field">
        <label for="title">Main Head Title</label>
        <input class="input" type="text" id="title" name="title" required maxlength="150" placeholder="e.g. Assets" autofocus />
      </div>
      <button type="submit" class="btn-save-dark">Save Main Head</button>
    </form>
  </div>

  <div class="card accent-left">
    <div class="card-pad" style="border-bottom:1px solid var(--line)">
      <h2 class="card-title" style="margin:0;font-size:20px">Main Head Table</h2>
    </div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Code</th><th>Title</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="2" class="empty-state">No main heads yet.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td class="num" style="color:var(--brand);font-weight:700"><?= e($r['code']) ?></td>
            <td><strong><?= e($r['title']) ?></strong></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
