<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Models\SubsidiaryHead;
use App\Services\LedgerService;

Auth::requireLoginApi();

// Optional single balance lookup
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    jsonResponse([
        'success' => true,
        'balance' => LedgerService::previousBalance($id),
    ]);
}

$accounts = SubsidiaryHead::forSelect();

// Optional filter by sub_head_id
if (isset($_GET['sub_head_id'])) {
    $sid = (int)$_GET['sub_head_id'];
    $db = \App\Core\Database::getInstance();
    $rows = $db->fetchAll(
        'SELECT s.id, s.full_code, s.title, s.nature, s.account_type, s.opening_balance
         FROM subsidiary_heads s
         WHERE s.sub_head_id = :sid AND s.is_active = 1
         ORDER BY s.full_code',
        ['sid' => $sid]
    );
    $accounts = array_map(static function ($r) {
        return [
            'id'    => (int)$r['id'],
            'code'  => $r['full_code'],
            'title' => $r['title'],
            'label' => $r['title'], // title only in dropdowns
            'nature'=> $r['nature'],
            'type'  => $r['account_type'],
            'balance'=> LedgerService::previousBalance((int)$r['id']),
        ];
    }, $rows);
}

jsonResponse(['success' => true, 'accounts' => $accounts]);
