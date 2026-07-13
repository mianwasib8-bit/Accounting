<?php
/**
 * PDF Voucher - Redirect to print for now
 * Later can be enhanced with TCPF or Dompdf
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;

Auth::requireLogin();

$id = (int)($_GET['id'] ?? 0);
$type = strtoupper(trim($_GET['type'] ?? ''));

if ($id <= 0 || !in_array($type, ['CRV', 'CPV', 'JV', 'PUR'], true)) {
    header('Location: ' . url('/modules/vouchers/list.php'));
    exit;
}

// For now, redirect to print version
// Later: implement actual PDF generation using TCPF or Dompdf
header('Location: ' . url('/modules/vouchers/print_voucher.php?id=' . $id . '&type=' . $type));
exit;
