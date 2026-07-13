<?php
/**
 * Cash Receipt — 2-row amount entry (row2 = cash/bank)
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Services\FinancialYearService;

$pageTitle = 'Cash Receipt Voucher';
$activePage = 'crv';
$pageScripts = ['/assets/js/voucher_cash.js'];

$fy = FinancialYearService::getActive();
$today = date('Y-m-d');

require dirname(__DIR__, 2) . '/includes/layout_start.php';
?>

<div class="section-head voucher-page-head">
  <div>
    <h2>Cash Receipt Voucher</h2>
    <p>Row 1 party · Row 2 cash/bank · Amount only · FY <?= e($fy['code'] ?? '—') ?></p>
  </div>
</div>

<form id="voucher-form" class="card accent-left voucher-card" data-type="CRV" novalidate>
  <div class="voucher-meta card-pad">
    <div class="voucher-meta-grid">
      <div class="field">
        <label>Type</label>
        <input class="input input-sm" type="text" value="CRV" readonly disabled />
        <input type="hidden" id="voucher_type" value="CRV" />
      </div>
      <div class="field">
        <label>Voucher No.</label>
        <input class="input input-sm input-mono" type="text" id="voucher_ref" value="…" readonly disabled tabindex="-1" />
      </div>
      <input type="hidden" id="sequence_no" value="…" />
      <div class="field">
        <label for="voucher_date">Date</label>
        <input class="input input-sm" type="date" id="voucher_date" value="<?= e($today) ?>" required />
      </div>
    </div>
  </div>

  <div class="voucher-lines-head card-pad flex-between">
    <div>
      <h3 style="margin:0;font-size:14px;font-weight:700">Entry Lines</h3>
      <p style="margin:2px 0 0;font-size:11px;color:var(--muted)">Min 2 rows · same total amount both sides</p>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" id="btn-add-row">+ Row</button>
  </div>

  <div class="table-wrap voucher-lines">
    <table class="data" style="min-width:680px">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Account</th>
          <th>Title</th>
          <th>Narration</th>
          <th class="text-right" style="width:110px">Amount</th>
          <th class="text-right" style="width:110px">Prev. Bal</th>
          <th style="width:40px"></th>
        </tr>
      </thead>
      <tbody id="lines-body"></tbody>
      <tfoot>
        <tr style="background:var(--surface-2)">
          <td colspan="4" style="text-align:right;font-weight:700;padding:10px 14px;font-size:13px">Total</td>
          <td class="text-right num" style="font-weight:800" id="total-amount">0.00</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="card-pad flex-between flex-wrap voucher-actions">
    <p style="margin:0;font-size:11px;color:var(--muted)">Auto: Cash Dr · Party Cr</p>
    <div class="flex gap-2">
      <a href="<?= e(url('/modules/vouchers/list.php')) ?>" class="btn btn-secondary btn-sm">Cancel</a>
      <button type="submit" class="btn btn-primary btn-sm" id="btn-save" disabled>Save Receipt</button>
    </div>
  </div>
</form>

<?php require dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
