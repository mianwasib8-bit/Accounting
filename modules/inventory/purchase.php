<?php
/**
 * Purchase Form
 * Invoice# auto (readonly) · Date · Bill# · Cash/Credit · Party + Prev Bal · Company
 * Lines: Item · Title · Packing · Batch · Qty · Rate · Amount · Disc% · Disc Amt · Amount · Disc% · Net
 * Footer: Total · Bill Expense · Net Amount — no search-by-date/party panels
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Item;
use App\Services\FinancialYearService;
use App\Services\LedgerService;

$pageTitle = 'Purchase Form';
$activePage = 'purchase';
$pageScripts = ['/assets/js/purchase.js'];

$fy = FinancialYearService::getActive();
$today = date('Y-m-d');
$items = Item::all();
$db = Database::getInstance();

// Get all accounts for party dropdown (suppliers, cash, bank)
$allAccounts = $db->fetchAll(
    "SELECT id, full_code, title, account_type
     FROM subsidiary_heads
     WHERE is_active = 1
     ORDER BY account_type = 'party' DESC, account_type IN ('cash','bank') DESC, title"
);

// Separate for JS: suppliers/parties and cash/bank
$supplierAccounts = array_filter($allAccounts, function($a) {
    return $a['account_type'] === 'party';
});
$cashBankAccounts = array_filter($allAccounts, function($a) {
    return in_array($a['account_type'], ['cash', 'bank']);
});

// Preload balances for all accounts
$partyBalances = [];
foreach ($allAccounts as $p) {
    $partyBalances[(int)$p['id']] = LedgerService::previousBalance((int)$p['id']);
}

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<div class="section-head voucher-page-head">
  <div>
    <h2>Purchase Form</h2>
    <p>Items from Item Adder · double discount · FY <?= e($fy['code'] ?? '—') ?></p>
  </div>
</div>

<form id="purchase-form" class="card accent-left purchase-card" novalidate>
  <div class="card-pad purchase-header">
    <div class="purchase-meta-grid">
      <div class="field">
        <label>Invoice # <span class="hint">(auto)</span></label>
        <input class="input input-sm input-mono" type="text" id="invoice_no" value="…" readonly disabled tabindex="-1" />
      </div>
      <div class="field">
        <label for="purchase_date">Date</label>
        <input class="input input-sm" type="date" id="purchase_date" value="<?= e($today) ?>" required />
      </div>
      <div class="field">
        <label for="bill_no">Pur Bill #</label>
        <input class="input input-sm" type="text" id="bill_no" maxlength="50" placeholder="Supplier bill no" />
      </div>
      <div class="field">
        <label for="pay_mode">Cash / Credit</label>
        <select class="select input-sm" id="pay_mode">
          <option value="Credit" selected>Credit</option>
          <option value="Cash">Cash</option>
        </select>
      </div>
      <div class="field">
        <label for="party_id">Account (Party / Cash / Bank)</label>
        <select class="select input-sm" id="party_id" required>
          <option value="">— Select account —</option>
          <?php foreach ($allAccounts as $a): ?>
            <option value="<?= (int)$a['id'] ?>" data-code="<?= e($a['full_code']) ?>" data-type="<?= e($a['account_type']) ?>">
              <?= e($a['title']) ?> (<?= e(strtoupper($a['account_type'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Party Code</label>
        <input class="input input-sm input-mono" type="text" id="party_code" value="—" readonly disabled />
      </div>
      <div class="field">
        <label>Prev Balance</label>
        <input class="input input-sm input-mono" type="text" id="party_balance" value="0.00" readonly disabled />
      </div>
      <div class="field">
        <label for="company_name">Company</label>
        <input class="input input-sm" type="text" id="company_name" maxlength="150" placeholder="Company name" />
      </div>
    </div>
  </div>

  <div class="card-pad flex-between" style="border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:10px 16px">
    <div>
      <h3 style="margin:0;font-size:14px;font-weight:700">Item Lines</h3>
      <p style="margin:2px 0 0;font-size:11px;color:var(--muted)">Code → title/packing auto · qty × rate · disc% × 2 → net</p>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" id="btn-add-row">+ Row</button>
  </div>

  <div class="table-wrap purchase-lines">
    <table class="data" style="min-width:1100px">
      <thead>
        <tr>
          <th style="width:36px">#</th>
          <th style="min-width:140px">Item</th>
          <th style="min-width:120px">Title</th>
          <th style="width:80px">Packing</th>
          <th style="width:90px">Batch No</th>
          <th class="text-right" style="width:70px">Qty</th>
          <th class="text-right" style="width:80px">Rate</th>
          <th class="text-right" style="width:90px">Amount</th>
          <th class="text-right" style="width:70px">Disc%</th>
          <th class="text-right" style="width:80px">Disc Amt</th>
          <th class="text-right" style="width:90px">Amount</th>
          <th class="text-right" style="width:70px">Disc%</th>
          <th class="text-right" style="width:90px">Net Amt</th>
          <th style="width:36px"></th>
        </tr>
      </thead>
      <tbody id="lines-body"></tbody>
    </table>
  </div>

  <div class="card-pad purchase-footer">
    <div class="purchase-totals">
      <div class="tot-row">
        <span>Total</span>
        <strong id="subtotal" class="num">0.00</strong>
      </div>
      <div class="tot-row">
        <label for="bill_expense">Bilty / Bill Exp</label>
        <input class="input input-sm" type="number" id="bill_expense" min="0" step="0.01" value="0.00" style="max-width:140px;text-align:right" />
      </div>
      <div class="tot-row tot-net">
        <span>Net Amount</span>
        <strong id="net_amount" class="num">0.00</strong>
      </div>
    </div>
    <div class="flex gap-2" style="justify-content:flex-end;margin-top:12px">
      <button type="button" class="btn btn-secondary btn-sm" id="btn-clear">Clear</button>
      <button type="submit" class="btn btn-primary btn-sm" id="btn-save" disabled>Save</button>
    </div>
  </div>
</form>

<script>
window.PURCHASE_BOOT = {
  items: <?= json_encode(array_map(static function ($i) {
      return [
          'id' => (int)$i['id'],
          'code' => $i['item_code'],
          'name' => $i['item_name'],
          'packing' => $i['packing'],
          'purchase_rate' => (float)$i['purchase_rate'],
          'stock' => (float)$i['stock'],
      ];
  }, $items), JSON_UNESCAPED_UNICODE) ?>,
  partyBalances: <?= json_encode($partyBalances) ?>,
  supplierAccounts: <?= json_encode(array_map(function($a) {
      return ['id' => (int)$a['id'], 'code' => $a['full_code'], 'title' => $a['title'], 'type' => $a['account_type']];
  }, $supplierAccounts)) ?>,
  cashAccounts: <?= json_encode(array_map(function($a) {
      return ['id' => (int)$a['id'], 'code' => $a['full_code'], 'title' => $a['title'], 'type' => $a['account_type']];
  }, $cashBankAccounts)) ?>
};
</script>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
