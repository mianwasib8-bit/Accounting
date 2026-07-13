<?php
/**
 * Layout — theme-aware sidebar with icons
 */
declare(strict_types=1);

use App\Core\Auth;

Auth::requireLogin();
$user = Auth::user();
$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';

function isActive(string $key, string $active): string
{
    return $key === $active ? 'active' : '';
}

function inGroup(array $keys, string $active): bool
{
    return in_array($active, $keys, true);
}

$headsOpen = inGroup(['main_head', 'sub_head', 'subsidiary_head', 'coa'], $activePage);
$inventoryOpen = inGroup(['item_category', 'item_adder', 'purchase'], $activePage);
$vouchersOpen = inGroup(['crv', 'cpv', 'jv', 'voucher_list'], $activePage);
$userOpen = inGroup(['profile', 'password'], $activePage);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
  <script>
    window.APP_BASE = <?= json_encode(BASE_URL) ?>;
    (function () {
      try {
        var t = localStorage.getItem('as_theme') || 'light';
        document.documentElement.setAttribute('data-theme', t);
      } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>?v=13" />
  <style>.hidden{display:none!important}</style>
</head>
<body>
<div id="app-shell" class="app-shell">
  <div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>

  <aside id="sidebar" class="sidebar" aria-label="Main navigation">
    <div class="brand">
      <div class="brand-mark">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v1m-7-6a9 9 0 1118 0 9 9 0 01-18 0z"/></svg>
      </div>
      <div class="brand-text">
        <strong><?= e(APP_NAME) ?></strong>
        <span><?= e(APP_TAGLINE) ?></span>
      </div>
      <button type="button" id="btn-sidebar-close" class="icon-btn mobile-only brand-close" aria-label="Close">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <nav class="nav-scroll">
      <a class="nav-item <?= isActive('dashboard', $activePage) ?>" href="<?= e(url('/index.php')) ?>">
        <span class="nav-ico">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10"/></svg>
        </span>
        <span class="sidebar-label nav-item-text">Dashboard</span>
      </a>

      <div class="nav-group <?= $headsOpen ? 'is-open' : '' ?>" data-nav-group>
        <button type="button" class="nav-item nav-parent <?= $headsOpen ? 'active-parent' : '' ?>" data-nav-parent aria-expanded="<?= $headsOpen ? 'true' : 'false' ?>">
          <span class="nav-ico">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h10M4 18h16"/></svg>
          </span>
          <span class="sidebar-label nav-item-text">Heads</span>
          <svg class="nav-chevron sidebar-label" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="nav-flyout">
          <a class="nav-sublink <?= isActive('main_head', $activePage) ?>" href="<?= e(url('/modules/heads/main_head.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14"/></svg>
            Main Head
          </a>
          <a class="nav-sublink <?= isActive('sub_head', $activePage) ?>" href="<?= e(url('/modules/heads/sub_head.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 6h12M8 12h12M8 18h12"/></svg>
            Sub Head
          </a>
          <a class="nav-sublink <?= isActive('subsidiary_head', $activePage) ?>" href="<?= e(url('/modules/heads/subsidiary_head.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5a2 2 0 012-2h10a2 2 0 012 2v14l-3-2-3 2-3-2-3 2V5z"/></svg>
            Subsidiary
          </a>
          <a class="nav-sublink <?= isActive('coa', $activePage) ?>" href="<?= e(url('/modules/heads/chart_of_accounts.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v6m0 0a3 3 0 100 6 3 3 0 000-6zm0 6v6M5 21h14"/></svg>
            Chart of Accounts
          </a>
        </div>
      </div>

      <div class="nav-group <?= $inventoryOpen ? 'is-open' : '' ?>" data-nav-group>
        <button type="button" class="nav-item nav-parent <?= $inventoryOpen ? 'active-parent' : '' ?>" data-nav-parent aria-expanded="<?= $inventoryOpen ? 'true' : 'false' ?>">
          <span class="nav-ico">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
          </span>
          <span class="sidebar-label nav-item-text">Inventory</span>
          <svg class="nav-chevron sidebar-label" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="nav-flyout">
          <a class="nav-sublink <?= isActive('item_category', $activePage) ?>" href="<?= e(url('/modules/inventory/category.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            Categories
          </a>
          <a class="nav-sublink <?= isActive('item_adder', $activePage) ?>" href="<?= e(url('/modules/inventory/item_adder.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4"/></svg>
            Items
          </a>
          <a class="nav-sublink <?= isActive('purchase', $activePage) ?>" href="<?= e(url('/modules/inventory/purchase.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.4.4-.1 1.1.4 1.1H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
            Purchase Form
          </a>
        </div>
      </div>

      <div class="nav-group <?= $vouchersOpen ? 'is-open' : '' ?>" data-nav-group>
        <button type="button" class="nav-item nav-parent <?= $vouchersOpen ? 'active-parent' : '' ?>" data-nav-parent aria-expanded="<?= $vouchersOpen ? 'true' : 'false' ?>">
          <span class="nav-ico">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </span>
          <span class="sidebar-label nav-item-text">Vouchers</span>
          <svg class="nav-chevron sidebar-label" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="nav-flyout">
          <a class="nav-sublink <?= isActive('crv', $activePage) ?>" href="<?= e(url('/modules/vouchers/cash_receipt.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4"/></svg>
            Cash Receipt
          </a>
          <a class="nav-sublink <?= isActive('cpv', $activePage) ?>" href="<?= e(url('/modules/vouchers/cash_payment.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 12H4"/></svg>
            Cash Payment
          </a>
          <a class="nav-sublink <?= isActive('jv', $activePage) ?>" href="<?= e(url('/modules/vouchers/journal_voucher.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            Journal Voucher
          </a>
          <a class="nav-sublink <?= isActive('voucher_list', $activePage) ?>" href="<?= e(url('/modules/vouchers/list.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h10"/></svg>
            Voucher List
          </a>
        </div>
      </div>

      <div class="nav-group <?= $userOpen ? 'is-open' : '' ?>" data-nav-group>
        <button type="button" class="nav-item nav-parent <?= $userOpen ? 'active-parent' : '' ?>" data-nav-parent aria-expanded="<?= $userOpen ? 'true' : 'false' ?>">
          <span class="nav-ico">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21a8 8 0 1116 0"/></svg>
          </span>
          <span class="sidebar-label nav-item-text">User</span>
          <svg class="nav-chevron sidebar-label" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="nav-flyout">
          <a class="nav-sublink <?= isActive('profile', $activePage) ?>" href="<?= e(url('/modules/user/profile.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5.121 17.804A9 9 0 1118.88 17.8M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Profile
          </a>
          <a class="nav-sublink <?= isActive('password', $activePage) ?>" href="<?= e(url('/modules/user/change_password.php')) ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Change Password
          </a>
        </div>
      </div>
    </nav>

    <div class="sidebar-foot">
      <div class="user-chip">
        <div class="avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))) ?></div>
        <div class="user-meta sidebar-label">
          <strong><?= e($user['full_name'] ?? 'Admin') ?></strong>
          <span><?= e($user['role'] ?? 'admin') ?></span>
        </div>
      </div>
      <a href="<?= e(url('/logout.php')) ?>" class="btn-logout">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1"/></svg>
        <span class="sidebar-label">Logout</span>
      </a>
    </div>
  </aside>

  <div class="main-wrap">
    <header class="topbar">
      <button type="button" id="btn-sidebar-open" class="icon-btn menu-btn" aria-label="Open menu">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <div class="page-title">
        <h1><?= e($pageTitle) ?></h1>
        <p class="page-sub"><?= e(APP_TAGLINE) ?></p>
      </div>
      <div class="topbar-actions">
        <div id="theme-switch-wrap" class="ios-switch" title="Toggle light / dark" role="button" tabindex="0">
          <input type="checkbox" id="theme-switch" aria-label="Dark mode" />
          <span class="ios-track">
            <span class="ios-knob"></span>
            <span class="ios-label-on">Dark</span>
            <span class="ios-label-off">Light</span>
          </span>
        </div>
      </div>
    </header>
    <main class="content">
