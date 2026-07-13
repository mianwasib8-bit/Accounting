<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;

class ItemCategory
{
    public static function all(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM item_categories';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY code';
        return Database::getInstance()->fetchAll($sql);
    }

    public static function find(int $id): ?array
    {
        return Database::getInstance()->fetch(
            'SELECT * FROM item_categories WHERE id = :id',
            ['id' => $id]
        );
    }

    /** Next category code: 01, 02, 03… (2 digits) */
    public static function nextCode(): string
    {
        $rows = Database::getInstance()->fetchAll('SELECT code FROM item_categories');
        $max = 0;
        foreach ($rows as $r) {
            $n = (int)preg_replace('/\D/', '', (string)$r['code']);
            if ($n > $max) {
                $max = $n;
            }
        }
        return str_pad((string)($max + 1), 2, '0', STR_PAD_LEFT);
    }

    public static function create(string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Category title is required.');
        }
        $db = Database::getInstance();
        $exists = $db->fetch(
            'SELECT id FROM item_categories WHERE LOWER(title) = LOWER(:t) AND is_active = 1 LIMIT 1',
            ['t' => $title]
        );
        if ($exists) {
            throw new \InvalidArgumentException('Category already exists.');
        }

        $code = self::nextCode();
        $db->query(
            'INSERT INTO item_categories (code, title, created_by) VALUES (:c, :t, :u)',
            ['c' => $code, 't' => $title, 'u' => Auth::id()]
        );
        $id = (int)$db->lastInsertId();
        Audit::log(Auth::id(), 'create', 'item_category', (string)$id, "Category {$code} — {$title}");
        return ['id' => $id, 'code' => $code, 'title' => $title];
    }

    public static function update(int $id, string $title): array
    {
        $title = trim($title);
        if ($id <= 0 || $title === '') {
            throw new \InvalidArgumentException('Category and title are required.');
        }
        $row = self::find($id);
        if (!$row || !(int)$row['is_active']) {
            throw new \InvalidArgumentException('Category not found.');
        }
        $db = Database::getInstance();
        $dup = $db->fetch(
            'SELECT id FROM item_categories WHERE LOWER(title) = LOWER(:t) AND id <> :id AND is_active = 1 LIMIT 1',
            ['t' => $title, 'id' => $id]
        );
        if ($dup) {
            throw new \InvalidArgumentException('Another category with this name exists.');
        }
        // code stays fixed
        $db->query(
            'UPDATE item_categories SET title = :t WHERE id = :id',
            ['t' => $title, 'id' => $id]
        );
        Audit::log(Auth::id(), 'update', 'item_category', (string)$id, "Updated category {$row['code']}");
        return ['id' => $id, 'code' => $row['code'], 'title' => $title];
    }

    public static function softDelete(int $id): void
    {
        $row = self::find($id);
        if (!$row) {
            throw new \InvalidArgumentException('Category not found.');
        }
        $cnt = (int)Database::getInstance()->fetchColumn(
            'SELECT COUNT(*) FROM items WHERE category_id = :id AND is_active = 1',
            ['id' => $id]
        );
        if ($cnt > 0) {
            throw new \InvalidArgumentException('Category has items. Delete or move items first.');
        }
        Database::getInstance()->query(
            'UPDATE item_categories SET is_active = 0 WHERE id = :id',
            ['id' => $id]
        );
        Audit::log(Auth::id(), 'delete', 'item_category', (string)$id, "Deleted category {$row['code']}");
    }
}
