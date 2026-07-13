<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;

class Item
{
    public static function all(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT i.*, c.code AS category_code, c.title AS category_title
             FROM items i
             INNER JOIN item_categories c ON c.id = i.category_id
             WHERE i.is_active = 1
             ORDER BY i.item_code'
        );
    }

    public static function find(int $id): ?array
    {
        return Database::getInstance()->fetch(
            'SELECT i.*, c.code AS category_code, c.title AS category_title
             FROM items i
             INNER JOIN item_categories c ON c.id = i.category_id
             WHERE i.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Next item code under category:
     * category (2 digits) + item serial (2 digits) e.g. 0101, 0102, 0201
     */
    public static function nextCode(int $categoryId): string
    {
        $db = Database::getInstance();
        $cat = $db->fetch('SELECT code FROM item_categories WHERE id = :id', ['id' => $categoryId]);
        if (!$cat) {
            throw new \InvalidArgumentException('Invalid category.');
        }
        $prefix = preg_replace('/\D/', '', (string)$cat['code']);
        $prefix = str_pad(substr($prefix, 0, 2), 2, '0', STR_PAD_LEFT);

        $rows = $db->fetchAll(
            'SELECT item_code FROM items WHERE category_id = :cid',
            ['cid' => $categoryId]
        );
        $max = 0;
        foreach ($rows as $r) {
            $digits = preg_replace('/\D/', '', (string)$r['item_code']);
            // last 2 digits = item serial under category
            if (strlen($digits) >= 2) {
                $serial = (int)substr($digits, -2);
            } else {
                $serial = (int)$digits;
            }
            if ($serial > $max) {
                $max = $serial;
            }
        }
        $next = str_pad((string)($max + 1), 2, '0', STR_PAD_LEFT);
        return $prefix . $next;
    }

    public static function create(array $data): array
    {
        $categoryId = (int)($data['category_id'] ?? 0);
        $name = trim((string)($data['item_name'] ?? ''));
        $packing = trim((string)($data['packing'] ?? ''));
        $saleRate = round((float)($data['sale_rate'] ?? 0), 2);
        $purRate = round((float)($data['purchase_rate'] ?? 0), 2);
        $stock = round((float)($data['stock'] ?? 0), 3);

        if ($categoryId <= 0) {
            throw new \InvalidArgumentException('Select a category.');
        }
        if ($name === '') {
            throw new \InvalidArgumentException('Item name is required.');
        }
        if ($saleRate < 0 || $purRate < 0 || $stock < 0) {
            throw new \InvalidArgumentException('Rates and stock cannot be negative.');
        }

        $db = Database::getInstance();
        $cat = ItemCategory::find($categoryId);
        if (!$cat || !(int)$cat['is_active']) {
            throw new \InvalidArgumentException('Invalid or inactive category.');
        }

        $code = self::nextCode($categoryId);

        $db->query(
            'INSERT INTO items
               (category_id, item_code, item_name, packing, sale_rate, purchase_rate, stock, created_by)
             VALUES
               (:cid, :code, :name, :pack, :sr, :pr, :stk, :uid)',
            [
                'cid'  => $categoryId,
                'code' => $code,
                'name' => $name,
                'pack' => $packing !== '' ? mb_substr($packing, 0, 80) : null,
                'sr'   => $saleRate,
                'pr'   => $purRate,
                'stk'  => $stock,
                'uid'  => Auth::id(),
            ]
        );
        $id = (int)$db->lastInsertId();
        Audit::log(Auth::id(), 'create', 'item', (string)$id, "Item {$code} — {$name}");

        return self::find($id) ?? [
            'id' => $id,
            'item_code' => $code,
            'item_name' => $name,
        ];
    }

    public static function update(int $id, array $data): array
    {
        $row = self::find($id);
        if (!$row || !(int)$row['is_active']) {
            throw new \InvalidArgumentException('Item not found.');
        }

        $name = trim((string)($data['item_name'] ?? $row['item_name']));
        $packing = trim((string)($data['packing'] ?? ($row['packing'] ?? '')));
        $saleRate = round((float)($data['sale_rate'] ?? $row['sale_rate']), 2);
        $purRate = round((float)($data['purchase_rate'] ?? $row['purchase_rate']), 2);
        $stock = round((float)($data['stock'] ?? $row['stock']), 3);

        if ($name === '') {
            throw new \InvalidArgumentException('Item name is required.');
        }
        if ($saleRate < 0 || $purRate < 0 || $stock < 0) {
            throw new \InvalidArgumentException('Rates and stock cannot be negative.');
        }

        // category/code stay fixed after create
        Database::getInstance()->query(
            'UPDATE items
             SET item_name = :name, packing = :pack, sale_rate = :sr,
                 purchase_rate = :pr, stock = :stk
             WHERE id = :id',
            [
                'name' => $name,
                'pack' => $packing !== '' ? mb_substr($packing, 0, 80) : null,
                'sr'   => $saleRate,
                'pr'   => $purRate,
                'stk'  => $stock,
                'id'   => $id,
            ]
        );
        Audit::log(Auth::id(), 'update', 'item', (string)$id, "Updated item {$row['item_code']}");
        return self::find($id) ?? $row;
    }

    public static function softDelete(int $id): void
    {
        $row = self::find($id);
        if (!$row) {
            throw new \InvalidArgumentException('Item not found.');
        }
        Database::getInstance()->query(
            'UPDATE items SET is_active = 0 WHERE id = :id',
            ['id' => $id]
        );
        Audit::log(Auth::id(), 'delete', 'item', (string)$id, "Deleted item {$row['item_code']}");
    }
}
