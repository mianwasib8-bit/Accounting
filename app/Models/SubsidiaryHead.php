<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use App\Services\CodeGenerator;
use App\Services\LedgerService;

class SubsidiaryHead
{
    public static function all(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT s.*,
                    sh.title AS sub_title, sh.full_code AS sub_full_code,
                    mh.id AS main_id, mh.code AS main_code, mh.title AS main_title
             FROM subsidiary_heads s
             INNER JOIN sub_heads sh ON sh.id = s.sub_head_id
             INNER JOIN main_heads mh ON mh.id = sh.main_head_id
             WHERE s.is_active = 1
             ORDER BY s.full_code'
        );
    }

    public static function forSelect(): array
    {
        $rows = self::all();
        return array_map(static function ($r) {
            return [
                'id'    => (int)$r['id'],
                'code'  => $r['full_code'],
                'title' => $r['title'],
                // Dropdown shows TITLE only (code is still available separately)
                'label' => $r['title'],
                'nature'=> $r['nature'],
                'type'  => $r['account_type'],
                'balance'=> LedgerService::previousBalance((int)$r['id']),
                'path'  => $r['main_title'] . ' › ' . $r['sub_title'],
            ];
        }, $rows);
    }

    public static function cashAccounts(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT id, full_code, title, account_type
             FROM subsidiary_heads
             WHERE account_type IN ('cash','bank') AND is_active = 1
             ORDER BY full_code"
        );
    }

    /**
     * Create subsidiary with code + title only.
     * Opening balance / nature are managed later from Chart of Accounts.
     */
    public static function create(array $data): array
    {
        $subHeadId = (int)($data['sub_head_id'] ?? 0);
        $title = trim((string)($data['title'] ?? ''));
        $type = $data['account_type'] ?? 'general';
        if (!in_array($type, ['cash', 'bank', 'party', 'general'], true)) {
            $type = 'general';
        }
        if ($subHeadId <= 0 || $title === '') {
            throw new \InvalidArgumentException('Sub head and title are required.');
        }

        $codes = CodeGenerator::nextSubsidiaryCode($subHeadId);
        $db = Database::getInstance();
        $db->query(
            'INSERT INTO subsidiary_heads
               (sub_head_id, code, full_code, title, opening_balance, nature, account_type, created_by)
             VALUES
               (:sid, :c, :fc, :t, 0, \'Dr\', :at, :u)',
            [
                'sid' => $subHeadId,
                'c'   => $codes['code'],
                'fc'  => $codes['full_code'],
                't'   => $title,
                'at'  => $type,
                'u'   => Auth::id(),
            ]
        );
        $id = (int)$db->lastInsertId();
        Audit::log(Auth::id(), 'create', 'subsidiary_head', (string)$id, "Created {$codes['full_code']} — {$title}");
        return [
            'id' => $id,
            'code' => $codes['code'],
            'full_code' => $codes['full_code'],
            'title' => $title,
        ];
    }

    /**
     * Update opening balance, nature, account type (from Chart of Accounts).
     */
    public static function updateOpening(int $id, float $openingBalance, string $nature, ?string $accountType = null): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid account.');
        }
        $nature = $nature === 'Cr' ? 'Cr' : 'Dr';
        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM subsidiary_heads WHERE id = :id AND is_active = 1', ['id' => $id]);
        if (!$row) {
            throw new \InvalidArgumentException('Account not found.');
        }

        $params = [
            'ob' => round($openingBalance, 2),
            'n'  => $nature,
            'id' => $id,
        ];
        $sql = 'UPDATE subsidiary_heads SET opening_balance = :ob, nature = :n';
        if ($accountType !== null && in_array($accountType, ['cash', 'bank', 'party', 'general'], true)) {
            $sql .= ', account_type = :at';
            $params['at'] = $accountType;
        }
        $sql .= ' WHERE id = :id';
        $db->query($sql, $params);

        Audit::log(
            Auth::id(),
            'update',
            'subsidiary_head',
            (string)$id,
            "Updated opening for {$row['full_code']}: {$params['ob']} {$nature}"
        );

        return [
            'id' => $id,
            'full_code' => $row['full_code'],
            'title' => $row['title'],
            'opening_balance' => $params['ob'],
            'nature' => $nature,
            'balance' => LedgerService::previousBalance($id),
        ];
    }

    public static function count(): int
    {
        return (int)Database::getInstance()->fetchColumn(
            'SELECT COUNT(*) FROM subsidiary_heads WHERE is_active = 1'
        );
    }

    /** Full tree for Chart of Accounts */
    public static function chartTree(): array
    {
        $db = Database::getInstance();
        $mains = $db->fetchAll('SELECT * FROM main_heads WHERE is_active=1 ORDER BY code');
        $tree = [];
        foreach ($mains as $m) {
            $subs = $db->fetchAll(
                'SELECT * FROM sub_heads WHERE main_head_id = :m AND is_active=1 ORDER BY code',
                ['m' => $m['id']]
            );
            $subNodes = [];
            foreach ($subs as $s) {
                $leaves = $db->fetchAll(
                    'SELECT * FROM subsidiary_heads WHERE sub_head_id = :s AND is_active=1 ORDER BY code',
                    ['s' => $s['id']]
                );
                foreach ($leaves as &$leaf) {
                    $leaf['balance'] = LedgerService::previousBalance((int)$leaf['id']);
                }
                unset($leaf);
                $subNodes[] = [
                    'id' => $s['id'],
                    'code' => $s['full_code'],
                    'title' => $s['title'],
                    'subsidiaries' => $leaves,
                ];
            }
            $tree[] = [
                'id' => $m['id'],
                'code' => $m['code'],
                'title' => $m['title'],
                'subs' => $subNodes,
            ];
        }
        return $tree;
    }
}
