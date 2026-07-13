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

    /** Next category code: 01, 02, 03… */
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
            'SELECT id FROM item_categories WHERE LOWER(title) = LOWER(:t) LIMIT 1',
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
}
