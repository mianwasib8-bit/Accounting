<?php
/**
 * Dashboard — stats + charts only
 */
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Models\SubsidiaryHead;
use App\Services\FinancialYearService;
use App\Services\LedgerService;
use App\Services\VoucherService;

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$pageScripts = ['/assets/js/dashboard.js'];

$user = Auth::user();
$fy = FinancialYearService::getActive();
$db = Database::getInstance();

$totalAccounts = SubsidiaryHead::count();
$totalVouchers = VoucherService::countAll();
$cashBalance = LedgerService::totalCashBalance();

$crvCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM voucher_sequence WHERE voucher_type='CRV'");
$cpvCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM voucher_sequence WHERE voucher_type='CPV'");
$crvAmt = (float)$db->fetchColumn("SELECT COALESCE(SUM(total_amount),0) FROM cash_receipt_vouchers WHERE status='posted'");
$cpvAmt = (float)$db->fetchColumn("SELECT COALESCE(SUM(total_amount),0) FROM cash_payment_vouchers WHERE status='posted'");

$days = [];
$crvSeries = [];
$cpvSeries = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $days[] = date('D', strtotime($d));
    $crvSeries[] = (float)$db->fetchColumn(
        "SELECT COALESCE(SUM(total_amount),0) FROM cash_receipt_vouchers WHERE voucher_date = :d AND status='posted'",
        ['d' => $d]
    );
    $cpvSeries[] = (float)$db->fetchColumn(
        "SELECT COALESCE(SUM(total_amount),0) FROM cash_payment_vouchers WHERE voucher_date = :d AND status='posted'",
        ['d' => $d]
    );
}

$currency = (string)($db->fetchColumn("SELECT setting_value FROM settings WHERE setting_key='currency_symbol'") ?: 'Rs.');

require __DIR__ . '/includes/layout_start.php';
?>

<div class="welcome-banner">
  <div class="flex-between flex-wrap">
    <div>
      <h2>Welcome back, <?= e(explode(' ', $user['full_name'] ?? 'User')[0]) ?></h2>
      <p>
        Active FY: <strong><?= e($fy['code'] ?? '—') ?></strong>
        · Manage heads and post cash vouchers.
      </p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <a href="<?= e(url('/modules/vouchers/cash_receipt.php')) ?>" class="btn btn-secondary btn-sm" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.25);color:#fff">+ Receipt</a>
      <a href="<?= e(url('/modules/vouchers/cash_payment.php')) ?>" class="btn btn-secondary btn-sm" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.25);color:#fff">+ Payment</a>
    </div>
  </div>
</div>

<div class="stat-grid mb-5">
  <div class="stat-card">
    <div class="glow" style="background:#2563eb"></div>
    <div class="icon" style="background:linear-gradient(135deg,#2563eb,#1d4ed8)">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
    </div>
    <div class="label">Total Accounts</div>
    <div class="value"><?= number_format($totalAccounts) ?></div>
  </div>
  <div class="stat-card">
    <div class="glow" style="background:#0ea5e9"></div>
    <div class="icon" style="background:linear-gradient(135deg,#0ea5e9,#0284c7)">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    </div>
    <div class="label">Total Vouchers</div>
    <div class="value"><?= number_format($totalVouchers) ?></div>
  </div>
  <div class="stat-card">
    <div class="glow" style="background:#16a34a"></div>
    <div class="icon" style="background:linear-gradient(135deg,#22c55e,#16a34a)">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v1m-7-6a9 9 0 1118 0 9 9 0 01-18 0z"/></svg>
    </div>
    <div class="label">Cash Balance</div>
    <div class="value" style="font-size:20px"><?= e($currency) ?> <?= number_format($cashBalance, 2) ?></div>
  </div>
  <div class="stat-card">
    <div class="glow" style="background:#0f766e"></div>
    <div class="icon" style="background:linear-gradient(135deg,#14b8a6,#0d9488)">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
    </div>
    <div class="label">Net Cash Flow</div>
    <div class="value" style="font-size:20px"><?= e($currency) ?> <?= number_format($crvAmt - $cpvAmt, 2) ?></div>
  </div>
</div>

<div class="grid-2 equal">
  <div class="card card-pad">
    <div class="mb-4">
      <h3 style="margin:0;font-size:16px;font-weight:700">Voucher Mix</h3>
      <p style="margin:4px 0 0;font-size:12px;color:var(--muted)">CRV vs CPV count</p>
    </div>
    <div class="chart-box">
      <canvas id="chart-pie"></canvas>
    </div>
  </div>
  <div class="card card-pad">
    <div class="mb-4">
      <h3 style="margin:0;font-size:16px;font-weight:700">7-Day Cash Movement</h3>
      <p style="margin:4px 0 0;font-size:12px;color:var(--muted)">Receipts vs Payments</p>
    </div>
    <div class="chart-box">
      <canvas id="chart-bar"></canvas>
    </div>
  </div>
</div>

<script>
window.DASHBOARD_DATA = {
  pie: { crv: <?= (int)$crvCount ?>, cpv: <?= (int)$cpvCount ?> },
  bar: {
    labels: <?= json_encode($days) ?>,
    crv: <?= json_encode($crvSeries) ?>,
    cpv: <?= json_encode($cpvSeries) ?>
  }
};
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
