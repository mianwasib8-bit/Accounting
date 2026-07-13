<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Models\Item;

Auth::requireLoginApi();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        // Preview next item code for a category
        if (isset($_GET['next_code'])) {
            $cid = (int)($_GET['category_id'] ?? 0);
            if ($cid <= 0) {
                jsonResponse(['success' => false, 'message' => 'category_id required'], 422);
            }
            jsonResponse(['success' => true, 'item_code' => Item::nextCode($cid)]);
        }

        if (isset($_GET['id'])) {
            $row = Item::find((int)$_GET['id']);
            if (!$row) {
                jsonResponse(['success' => false, 'message' => 'Item not found'], 404);
            }
            jsonResponse(['success' => true, 'item' => $row]);
        }

        $items = Item::all();
        jsonResponse([
            'success' => true,
            'items' => array_map(static function ($i) {
                return [
                    'id'             => (int)$i['id'],
                    'category_id'    => (int)$i['category_id'],
                    'category_code'  => $i['category_code'],
                    'category_title' => $i['category_title'],
                    'item_code'      => $i['item_code'],
                    'item_name'      => $i['item_name'],
                    'packing'        => $i['packing'],
                    'sale_rate'      => (float)$i['sale_rate'],
                    'purchase_rate'  => (float)$i['purchase_rate'],
                    'stock'          => (float)$i['stock'],
                ];
            }, $items),
        ]);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    if ($method === 'POST') {
        $row = Item::create($data);
        jsonResponse(['success' => true, 'message' => 'Item saved.', 'data' => $row]);
    }

    if ($method === 'PUT' || ($method === 'POST' && !empty($data['_method']) && strtoupper($data['_method']) === 'PUT')) {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Item id required'], 422);
        }
        $row = Item::update($id, $data);
        jsonResponse(['success' => true, 'message' => 'Item updated.', 'data' => $row]);
    }

    if ($method === 'DELETE' || ($method === 'POST' && !empty($data['_method']) && strtoupper($data['_method']) === 'DELETE')) {
        $id = (int)($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Item id required'], 422);
        }
        Item::softDelete($id);
        jsonResponse(['success' => true, 'message' => 'Item deleted.']);
    }

    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
}
