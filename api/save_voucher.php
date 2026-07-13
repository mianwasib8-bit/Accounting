<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Services\VoucherService;

Auth::requireLoginApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    jsonResponse(['success' => false, 'message' => 'Invalid JSON'], 400);
}

$type = strtoupper((string)($data['voucher_type'] ?? ''));

try {
    if ($type === 'CRV') {
        $result = VoucherService::saveCashReceipt($data);
    } elseif ($type === 'CPV') {
        $result = VoucherService::saveCashPayment($data);
    } elseif ($type === 'JV') {
        $result = VoucherService::saveJournal($data);
    } else {
        jsonResponse(['success' => false, 'message' => 'Invalid voucher type'], 422);
    }
    jsonResponse([
        'success' => true,
        'message' => 'Voucher saved successfully.',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
}
