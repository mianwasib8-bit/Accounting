<?php
/**
 * Preview next auto codes for heads
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Services\CodeGenerator;

Auth::requireLoginApi();

$type = $_GET['type'] ?? '';

try {
    if ($type === 'main') {
        $code = CodeGenerator::nextMainHeadCode();
        jsonResponse(['success' => true, 'code' => $code, 'full_code' => $code]);
    }
    if ($type === 'sub') {
        $mainId = (int)($_GET['main_head_id'] ?? 0);
        $codes = CodeGenerator::nextSubHeadCode($mainId);
        jsonResponse(['success' => true] + $codes);
    }
    if ($type === 'subsidiary') {
        $subId = (int)($_GET['sub_head_id'] ?? 0);
        $codes = CodeGenerator::nextSubsidiaryCode($subId);
        jsonResponse(['success' => true] + $codes);
    }
    jsonResponse(['success' => false, 'message' => 'Invalid type'], 400);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
}
