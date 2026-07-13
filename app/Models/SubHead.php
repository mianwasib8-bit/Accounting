<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use App\Services\CodeGenerator;

class SubHead
{
    public static function all(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT sh.*, mh.code AS main_code, mh.title AS main_title
             FROM sub_heads sh
             INNER JOIN main_heads mh ON mh.id = sh.main_head_id
             WHERE sh.is_active = 1
             ORDER BY mh.code, sh.code'
        );
    }

    public static function byMain(int $mainHeadId): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM sub_heads WHERE main_head_id = :m AND is_active = 1 ORDER BY code',
            ['m' => $mainHeadId]
        );
    }

    public static function create(int $mainHeadId, string $title): array
    {
        $title = trim($title);
        if ($mainHeadId <= 0 || $title === '') {
            throw new \InvalidArgumentException('Main head and title are required.');
        }
        $codes = CodeGenerator::nextSubHeadCode($mainHeadId);
        $db = Database::getInstance();
        $db->query(
            'INSERT INTO sub_heads (main_head_id, code, full_code, title, created_by)
             VALUES (:m, :c, :fc, :t, :u)',
            [
                'm' => $mainHeadId,
                'c' => $codes['code'],
                'fc'=> $codes['full_code'],
                't' => $title,
                'u' => Auth::id(),
            ]
        );
        $id = (int)$db->lastInsertId();
        Audit::log(Auth::id(), 'create', 'sub_head', (string)$id, "Created sub head {$codes['full_code']} — {$title}");
        return [
            'id' => $id,
            'code' => $codes['code'],
            'full_code' => $codes['full_code'],
            'title' => $title,
            'main_code' => $codes['main_code'],
        ];
    }
}
