<?php
/**
 * View Voucher - Display voucher details
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\FinancialYearService;

Auth::requireLogin();

$pageTitle = 'View Voucher';
$activePage = 'voucher_list';

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$type = strtoupper(trim($_GET['type'] ?? ''));

if ($id <= 0 || !in_array($type, ['CRV', 'CPV', 'JV', 'PUR'], true)) {
    header('Location: ' . url('/modules/vouchers/list.php'));
    exit;
}

// Fetch voucher details
$voucher = null;
$lines = [];

if ($type === 'CRV') {
    $voucher = $db->fetch(
        'SELECT * FROM cash_receipt_vouchers WHERE id = :id',
        ['id' => $id]
    );
    $lines = $db->fetchAll(
        'SELECT * FROM cash_receipt_voucher_details WHERE voucher_id = :id',
        ['id' => $id]
    );
} elseif ($type === 'CPV') {
    $voucher = $db->fetch(
        'SELECT * FROM cash_payment_vouchers WHERE id = :id',
        ['id' => $id]
    );
    $lines = $db->fetchAll(
        'SELECT * FROM cash_payment_voucher_details WHERE voucher_id = :id',
        ['id' => $id]
    );
} elseif ($type === 'JV') {
    $voucher = $db->fetch(
        'SELECT * FROM journal_vouchers WHERE id = :id',
        ['id' => $id]
    );
    $lines = $db->fetchAll(
        'SELECT * FROM journal_voucher_details WHERE voucher_id = :id',
        ['id' => $id]
    );
} elseif ($type === 'PUR') {
    $voucher = $db->fetch(
        'SELECT * FROM purchases WHERE id = :id',
        ['id' => $id]
    );
    $lines = $db->fetchAll(
        'SELECT pl.*, i.item_name, i.item_code, i.packing FROM purchase_details pl LEFT JOIN items i ON i.id = pl.item_id WHERE pl.purchase_id = :id',
        ['id' => $id]
    );
}

if (!$voucher) {
    header('Location: ' . url('/modules/vouchers/list.php'));
    exit;
}

// Get account details for lines
foreach ($lines as &$line) {
    if ($type === 'PUR') {
        // Already joined with items
    } else {
        $account = $db->fetch(
            'SELECT full_code, title FROM subsidiary_heads WHERE id = :id',
            ['id' => $line['subsidiary_id']]
        );
        $line['account_code'] = $account['full_code'] ?? '';
        $line['account_title'] = $account['title'] ?? '';
    }
}

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<div class="section-head">
  <div>
    <h2>View <?php echo e($type); ?> Voucher</h2>
    <p>Voucher #<?= e($voucher['voucher_no'] ?? $voucher['id']) ?> · <?= e($voucher['voucher_date'] ?? '') ?></p>
  </div>
  <a href="<?= e(url('/modules/vouchers/list.php')) ?>" class="btn btn-secondary btn-sm no-print">← Back to List</a>
</div>

<div class="card">
  <div class="card-pad" style="border-bottom:1px solid var(--line)">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
      <div class="field">
        <label>Voucher Type</label>
        <div style="font-weight:600"><?= e($type) ?></div>
      </div>
      <div class="field">
        <label>Voucher No.</label>
        <div style="font-weight:600"><?= e($voucher['voucher_no'] ?? $voucher['id']) ?></div>
      </div>
      <div class="field">
        <label>Date</label>
        <div style="font-weight:600"><?= e($voucher['voucher_date'] ?? '') ?></div>
      </div>
      <?php if ($type === 'PUR'): ?>
        <div class="field">
          <label>Invoice No.</label>
          <div style="font-weight:600"><?= e($voucher['invoice_no'] ?? '') ?></div>
        </div>
        <div class="field">
          <label>Party</label>
          <div style="font-weight:600">
            <?php 
            $party = $db->fetch('SELECT title FROM subsidiary_heads WHERE id = :id', ['id' => $voucher['party_id']]);
            echo e($party['title'] ?? '');
            ?>
          </div>
        </div>
        <div class="field">
          <label>Net Amount</label>
          <div style="font-weight:600"><?= number_format((float)($voucher['net_amount'] ?? 0), 2) ?></div>
        </div>
      <?php else: ?>
        <div class="field">
          <label>Total Amount</label>
          <div style="font-weight:600"><?= number_format((float)($voucher['total_amount'] ?? $voucher['total_debit'] ?? 0), 2) ?></div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data" style="width:100%">
      <thead>
        <tr>
          <th>#</th>
          <?php if ($type === 'PUR'): ?>
            <th>Item Code</th>
            <th>Item Name</th>
            <th>Packing</th>
            <th>Batch</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Rate</th>
            <th class="text-right">Net Amount</th>
          <?php else: ?>
            <th>Account</th>
            <th>Title</th>
            <th>Narration</th>
            <?php if ($type === 'JV'): ?>
              <th class="text-right">Debit</th>
              <th class="text-right">Credit</th>
            <?php else: ?>
              <th class="text-right">Amount</th>
            <?php endif; ?>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($lines)): ?>
          <tr><td colspan="10" class="empty-state">No lines found.</td></tr>
        <?php else: ?>
          <?php foreach ($lines as $index => $line): ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <?php if ($type === 'PUR'): ?>
                <td><?= e($line['item_code'] ?? '') ?></td>
                <td><?= e($line['item_name'] ?? '') ?></td>
                <td><?= e($line['packing'] ?? '') ?></td>
                <td><?= e($line['batch_no'] ?? '') ?></td>
                <td class="text-right num"><?= number_format((float)($line['qty'] ?? 0), 3) ?></td>
                <td class="text-right num"><?= number_format((float)($line['rate'] ?? 0), 2) ?></td>
                <td class="text-right num"><?= number_format((float)($line['net_amount'] ?? $line['amount'] ?? 0), 2) ?></td>
              <?php else: ?>
                <td><?= e($line['account_code'] ?? '') ?> <?= e($line['account_title'] ?? '') ?></td>
                <td><?= e($line['account_title'] ?? '') ?></td>
                <td><?= e($line['narration'] ?? '') ?></td>
                <?php if ($type === 'JV'): ?>
                  <td class="text-right num"><?= number_format((float)($line['debit_amount'] ?? 0), 2) ?></td>
                  <td class="text-right num"><?= number_format((float)($line['credit_amount'] ?? 0), 2) ?></td>
                <?php else: ?>
                  <td class="text-right num"><?= number_format((float)($line['amount'] ?? 0), 2) ?></td>
                <?php endif; ?>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if ($type !== 'PUR'): ?>
      <tfoot>
        <tr style="background:var(--surface-2)">
          <td colspan="<?= $type === 'JV' ? 4 : 3 ?>" style="text-align:right;font-weight:700;padding:10px 14px;font-size:13px">Total</td>
          <?php if ($type === 'JV'): ?>
            <td class="text-right num" style="font-weight:800"><?= number_format((float)($voucher['total_debit'] ?? 0), 2) ?></td>
            <td class="text-right num" style="font-weight:800"><?= number_format((float)($voucher['total_credit'] ?? 0), 2) ?></td>
          <?php else: ?>
            <td class="text-right num" style="font-weight:800"><?= number_format((float)($voucher['total_amount'] ?? 0), 2) ?></td>
          <?php endif; ?>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<div class="flex gap-2 no-print" style="margin-top:16px">
  <a href="print_voucher.php?id=<?= (int)$id ?>&type=<?= e($type) ?>" class="btn btn-secondary btn-sm" target="_blank">
    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
    </svg>
    Print
  </a>
  <a href="pdf_voucher.php?id=<?= (int)$id ?>&type=<?= e($type) ?>" class="btn btn-secondary btn-sm" target="_blank">
    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    PDF
  </a>
</div>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
