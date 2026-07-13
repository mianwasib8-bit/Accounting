<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Services\VoucherService;

Auth::requireLoginApi();

$type = strtoupper($_GET['type'] ?? 'CRV');
$date = $_GET['date'] ?? date('Y-m-d');

try {
    $preview = VoucherService::previewNext($type, $date);
    jsonResponse([
        'success' => true,
        'voucher_no' => $preview['voucher_no'],
        'voucher_ref' => $preview['voucher_ref'],
        'sequence_no' => $preview['sequence_no'],
        'fy' => [
            'id' => (int)$preview['fy']['id'],
            'code' => $preview['fy']['code'],
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
}
