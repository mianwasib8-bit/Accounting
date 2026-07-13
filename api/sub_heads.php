<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Models\SubHead;

Auth::requireLoginApi();

$mainId = (int)($_GET['main_head_id'] ?? 0);
$items = $mainId > 0 ? SubHead::byMain($mainId) : [];
jsonResponse([
    'success' => true,
    'items' => array_map(static function ($r) {
        return [
            'id' => (int)$r['id'],
            'code' => $r['code'],
            'full_code' => $r['full_code'],
            'title' => $r['title'],
        ];
    }, $items),
]);
