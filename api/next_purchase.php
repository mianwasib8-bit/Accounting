<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Services\PurchaseService;

Auth::requireLoginApi();

$date = $_GET['date'] ?? date('Y-m-d');

try {
    $preview = PurchaseService::previewNext($date);
    jsonResponse([
        'success'    => true,
        'invoice_no' => $preview['invoice_no'],
        'voucher_no' => $preview['voucher_no'],
        'sequence_no'=> $preview['sequence_no'],
        'fy' => [
            'id'   => (int)$preview['fy']['id'],
            'code' => $preview['fy']['code'],
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
}
