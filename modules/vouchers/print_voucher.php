<?php
/**
 * Print Voucher - A4 Size Printable Version
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\FinancialYearService;

Auth::requireLogin();

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
$companyInfo = [];

if ($type === 'CRV') {
    $voucher = $db->fetch(
        'SELECT * FROM cash_receipt_vouchers WHERE id = :id',
        ['id' => $id]
    );
    $lines = $db->fetchAll(
        'SELECT * FROM cash_receipt_lines WHERE voucher_id = :id',
        ['id' => $id]
    );
} elseif ($type === 'CPV') {
    $voucher = $db->fetch(
        'SELECT * FROM cash_payment_vouchers WHERE id = :id',
        ['id' => $id]
    );
    $lines = $db->fetchAll(
        'SELECT * FROM cash_payment_lines WHERE voucher_id = :id',
        ['id' => $id]
    );
} elseif ($type === 'JV') {
    $voucher = $db->fetch(
        'SELECT * FROM journal_vouchers WHERE id = :id',
        ['id' => $id]
    );
    $lines = $db->fetchAll(
        'SELECT * FROM journal_lines WHERE voucher_id = :id',
        ['id' => $id]
    );
} elseif ($type === 'PUR') {
    $voucher = $db->fetch(
        'SELECT * FROM purchases WHERE id = :id',
        ['id' => $id]
    );
    $lines = $db->fetchAll(
        'SELECT pl.*, i.item_name, i.item_code, i.packing FROM purchase_lines pl LEFT JOIN items i ON i.id = pl.item_id WHERE pl.purchase_id = :id',
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

// Get company info
$companyInfo = $db->fetch("SELECT * FROM app_settings WHERE setting_key = 'company_name' LIMIT 1");
$companyName = $companyInfo ? $companyInfo['setting_value'] : APP_NAME;
$companyAddress = '';
$companyPhone = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Print <?= e($type) ?> #<?= e($voucher['voucher_no'] ?? $voucher['id']) ?> · <?= e(APP_NAME) ?></title>
  <style>
    @page {
      size: A4;
      margin: 15mm;
    }
    body {
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 11pt;
      margin: 0;
      padding: 0;
      color: #333;
    }
    .header {
      border-bottom: 2px solid #333;
      padding-bottom: 10px;
      margin-bottom: 15px;
    }
    .company-name {
      font-size: 18pt;
      font-weight: bold;
      text-align: center;
      margin-bottom: 5px;
    }
    .voucher-title {
      font-size: 14pt;
      font-weight: bold;
      text-align: center;
      margin-bottom: 10px;
    }
    .voucher-meta {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-bottom: 15px;
    }
    .meta-item {
      display: flex;
      justify-content: space-between;
    }
    .meta-label {
      font-weight: bold;
      color: #666;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 15px;
    }
    th, td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
    }
    th {
      background-color: #f5f5f5;
      font-weight: bold;
    }
    .text-right {
      text-align: right !important;
    }
    .text-center {
      text-align: center !important;
    }
    .footer {
      border-top: 2px solid #333;
      padding-top: 10px;
      margin-top: 15px;
      font-size: 10pt;
    }
    .total-row {
      font-weight: bold;
      background-color: #f9f9f9;
    }
    .num {
      font-family: monospace;
    }
  </style>
</head>
<body>
  <div class="header">
    <div class="company-name"><?= e($companyName) ?></div>
    <div style="text-align:center;color:#666;font-size:10pt"><?= e(APP_TAGLINE) ?></div>
  </div>

  <div class="voucher-title">
    <?= e($type) ?> VOUCHER
  </div>

  <div class="voucher-meta">
    <div class="meta-item">
      <span class="meta-label">Voucher No:</span>
      <span><?= e($voucher['voucher_no'] ?? $voucher['id']) ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label">Date:</span>
      <span><?= e($voucher['voucher_date'] ?? '') ?></span>
    </div>
    <div class="meta-item">
      <span class="meta-label">FY:</span>
      <span>
        <?php
        $fy = FinancialYearService::getByDate($voucher['voucher_date'] ?? '');
        echo e($fy['code'] ?? '');
        ?>
      </span>
    </div>
    <?php if ($type === 'PUR'): ?>
      <div class="meta-item">
        <span class="meta-label">Invoice No:</span>
        <span><?= e($voucher['invoice_no'] ?? '') ?></span>
      </div>
      <div class="meta-item">
        <span class="meta-label">Party:</span>
        <span>
          <?php
          $party = $db->fetch('SELECT title FROM subsidiary_heads WHERE id = :id', ['id' => $voucher['party_id']]);
          echo e($party['title'] ?? '');
          ?>
        </span>
      </div>
      <div class="meta-item">
        <span class="meta-label">Mode:</span>
        <span><?= e($voucher['pay_mode'] ?? '') ?></span>
      </div>
    <?php endif; ?>
  </div>

  <table>
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
        <tr><td colspan="10">No lines found.</td></tr>
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
    <tfoot>
      <tr class="total-row">
        <td colspan="<?= $type === 'PUR' ? 7 : ($type === 'JV' ? 4 : 3) ?>" style="text-align:right">Total:</td>
        <?php if ($type === 'PUR'): ?>
          <td class="text-right num"><?= number_format((float)($voucher['net_amount'] ?? 0), 2) ?></td>
        <?php elseif ($type === 'JV'): ?>
          <td class="text-right num"><?= number_format((float)($voucher['total_debit'] ?? 0), 2) ?></td>
          <td class="text-right num"><?= number_format((float)($voucher['total_credit'] ?? 0), 2) ?></td>
        <?php else: ?>
          <td class="text-right num"><?= number_format((float)($voucher['total_amount'] ?? 0), 2) ?></td>
        <?php endif; ?>
      </tr>
    </tfoot>
  </table>

  <div class="footer">
    <div style="text-align:center;color:#888">
      Printed on: <?= date('Y-m-d H:i:s') ?> | <?= e(APP_NAME) ?>
    </div>
  </div>

  <script>
    window.onload = function() {
      window.print();
      // Close the window after printing
      setTimeout(function() {
        window.close();
      }, 500);
    };
  </script>
</body>
</html>
