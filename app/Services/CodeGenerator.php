<?php
/**
 * Auto code generators — pure numeric digits only (no dashes)
 * Main:       01, 02…          (2 digits)
 * Sub full:   0101, 0102…      (main 2 + sub 2 = 4)
 * Subsidiary: 010100001…       (main 2 + sub 2 + subsi 5 = 9 digits)
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class CodeGenerator
{
    /** Next main head code: 01, 02, … */
    public static function nextMainHeadCode(): string
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll('SELECT code FROM main_heads');
        $max = 0;
        foreach ($rows as $r) {
            $n = (int)preg_replace('/\D/', '', (string)$r['code']);
            if ($n > $max) {
                $max = $n;
            }
        }
        return str_pad((string)($max + 1), 2, '0', STR_PAD_LEFT);
    }

    /** Next sub under main: local 01 + full_code = main+sub e.g. 0101 */
    public static function nextSubHeadCode(int $mainHeadId): array
    {
        $db = Database::getInstance();
        $main = $db->fetch('SELECT code FROM main_heads WHERE id = :id', ['id' => $mainHeadId]);
        if (!$main) {
            throw new \InvalidArgumentException('Invalid main head.');
        }
        $mainCode = preg_replace('/\D/', '', (string)$main['code']);
        $mainCode = str_pad(substr($mainCode, 0, 2), 2, '0', STR_PAD_LEFT);

        $max = $db->fetchColumn(
            'SELECT MAX(CAST(code AS UNSIGNED)) FROM sub_heads WHERE main_head_id = :mid',
            ['mid' => $mainHeadId]
        );
        $next = ((int)$max) + 1;
        $code = str_pad((string)$next, 2, '0', STR_PAD_LEFT);

        return [
            'code'      => $code,
            'full_code' => $mainCode . $code,
            'main_code' => $mainCode,
        ];
    }

    /**
     * Next subsidiary under sub head.
     * Local segment: 5 digits (00001, 00002…)
     * Full code: sub full (4 digits) + 5 = 9 digits e.g. 010100001
     */
    public static function nextSubsidiaryCode(int $subHeadId): array
    {
        $db = Database::getInstance();
        $sub = $db->fetch(
            'SELECT sh.full_code, sh.code FROM sub_heads sh WHERE sh.id = :id',
            ['id' => $subHeadId]
        );
        if (!$sub) {
            throw new \InvalidArgumentException('Invalid sub head.');
        }
        $subFull = preg_replace('/\D/', '', (string)$sub['full_code']);
        // ensure 4-digit parent prefix when possible
        if (strlen($subFull) < 4) {
            $subFull = str_pad($subFull, 4, '0', STR_PAD_LEFT);
        }

        $max = $db->fetchColumn(
            'SELECT MAX(CAST(code AS UNSIGNED)) FROM subsidiary_heads WHERE sub_head_id = :sid',
            ['sid' => $subHeadId]
        );
        $next = ((int)$max) + 1;
        $code = str_pad((string)$next, 5, '0', STR_PAD_LEFT);

        return [
            'code'      => $code,
            'full_code' => $subFull . $code,
            'sub_full'  => $subFull,
        ];
    }
}
