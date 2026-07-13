<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Services\PurchaseService;

Auth::requireLoginApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    jsonResponse(['success' => false, 'message' => 'Invalid JSON'], 400);
}

try {
    $result = PurchaseService::save($data);
    jsonResponse([
        'success' => true,
        'message' => 'Purchase saved successfully.',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
}
