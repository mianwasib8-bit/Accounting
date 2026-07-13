<?php
/**
 * Update subsidiary opening balance / nature / type (Chart of Accounts)
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Models\SubsidiaryHead;

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
    $result = SubsidiaryHead::updateOpening(
        (int)($data['id'] ?? 0),
        (float)($data['opening_balance'] ?? 0),
        (string)($data['nature'] ?? 'Dr'),
        isset($data['account_type']) ? (string)$data['account_type'] : null
    );
    jsonResponse(['success' => true, 'message' => 'Updated', 'data' => $result]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
}
