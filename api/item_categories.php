<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Models\ItemCategory;

Auth::requireLoginApi();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $cats = ItemCategory::all();
        jsonResponse([
            'success' => true,
            'next_code' => ItemCategory::nextCode(),
            'categories' => array_map(static function ($c) {
                return [
                    'id'    => (int)$c['id'],
                    'code'  => $c['code'],
                    'title' => $c['title'],
                ];
            }, $cats),
        ]);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) {
            $data = $_POST;
        }
        $title = trim((string)($data['title'] ?? ''));
        $row = ItemCategory::create($title);
        jsonResponse(['success' => true, 'message' => 'Category saved.', 'data' => $row]);
    }

    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
}
