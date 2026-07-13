<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use App\Services\CodeGenerator;

class MainHead
{
    public static function all(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM main_heads WHERE is_active = 1 ORDER BY code'
        );
    }

    public static function create(string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Title is required.');
        }
        $db = Database::getInstance();
        $code = CodeGenerator::nextMainHeadCode();
        $db->query(
            'INSERT INTO main_heads (code, title, created_by) VALUES (:c, :t, :u)',
            ['c' => $code, 't' => $title, 'u' => Auth::id()]
        );
        $id = (int)$db->lastInsertId();
        Audit::log(Auth::id(), 'create', 'main_head', (string)$id, "Created main head {$code} — {$title}");
        return ['id' => $id, 'code' => $code, 'title' => $title];
    }

    public static function find(int $id): ?array
    {
        return Database::getInstance()->fetch('SELECT * FROM main_heads WHERE id = :id', ['id' => $id]);
    }
}
