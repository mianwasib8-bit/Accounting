<?php
/**
 * Voucher list — shared sequence (CRV + CPV + JV)
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;

$pageTitle = 'Voucher List';
$activePage = 'voucher_list';

$db = Database::getInstance();
$type = strtoupper(trim($_GET['type'] ?? ''));
$params = [];
$sql = 'SELECT vs.*, fy.code AS fy_code,
          CASE
            WHEN vs.voucher_type = \'CRV\' THEN (SELECT total_amount FROM cash_receipt_vouchers WHERE id = vs.voucher_id)
            WHEN vs.voucher_type = \'CPV\' THEN (SELECT total_amount FROM cash_payment_vouchers WHERE id = vs.voucher_id)
            WHEN vs.voucher_type = \'JV\'  THEN (SELECT total_debit FROM journal_vouchers WHERE id = vs.voucher_id)
            WHEN vs.voucher_type = \'PUR\' THEN (SELECT net_amount FROM purchases WHERE id = vs.voucher_id)
            ELSE 0
          END AS total_amount
        FROM voucher_sequence vs
        INNER JOIN financial_years fy ON fy.id = vs.financial_year_id
        WHERE 1=1';
if (in_array($type, ['CRV', 'CPV', 'JV', 'PUR'], true)) {
    $sql .= ' AND vs.voucher_type = :t';
    $params['t'] = $type;
}
$sql .= ' ORDER BY vs.financial_year_id DESC, vs.sequence_no DESC LIMIT 300';
$rows = $db->fetchAll($sql, $params);

$typePill = [
  'CRV' => 'pill-emerald',
  'CPV' => 'pill-rose',
  'JV'  => 'pill-sky',
  'PUR' => 'pill-amber',
];

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<div class="section-head">
  <div>
    <h2>Voucher List</h2>
    <p>Shared sequence across CRV · CPV · JV · PUR</p>
  </div>
  <div class="flex gap-2 flex-wrap">
    <a href="<?= e(url('/modules/vouchers/cash_receipt.php')) ?>" class="btn btn-primary btn-sm">+ CRV</a>
    <a href="<?= e(url('/modules/vouchers/cash_payment.php')) ?>" class="btn btn-secondary btn-sm">+ CPV</a>
    <a href="<?= e(url('/modules/vouchers/journal_voucher.php')) ?>" class="btn btn-secondary btn-sm">+ JV</a>
    <a href="<?= e(url('/modules/inventory/purchase.php')) ?>" class="btn btn-secondary btn-sm">+ PUR</a>
  </div>
</div>

<form method="GET" class="card card-pad mb-4 flex gap-3 flex-wrap items-center">
  <select name="type" class="select" style="max-width:180px">
    <option value="">All types</option>
    <option value="CRV" <?= $type === 'CRV' ? 'selected' : '' ?>>CRV</option>
    <option value="CPV" <?= $type === 'CPV' ? 'selected' : '' ?>>CPV</option>
    <option value="JV"  <?= $type === 'JV'  ? 'selected' : '' ?>>JV</option>
    <option value="PUR" <?= $type === 'PUR' ? 'selected' : '' ?>>PUR</option>
  </select>
  <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
</form>

<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Sequence</th>
          <th>Voucher No.</th>
          <th>Type</th>
          <th>FY</th>
          <th>Date</th>
          <th class="text-right">Amount</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="empty-state">No vouchers posted yet.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td class="num"><strong>#<?= (int)$r['sequence_no'] ?></strong></td>
          <td><strong class="num"><?= (int)$r['voucher_no'] ?></strong></td>
          <td>
            <span class="pill <?= $typePill[$r['voucher_type']] ?? 'pill-indigo' ?>">
              <?= e($r['voucher_type']) ?>
            </span>
          </td>
          <td><?= e($r['fy_code']) ?></td>
          <td><?= e($r['voucher_date']) ?></td>
          <td class="text-right num"><?= number_format((float)$r['total_amount'], 2) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= e($r['created_at']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
