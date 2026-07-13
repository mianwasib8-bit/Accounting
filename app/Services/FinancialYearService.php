<?php
/**
 * Financial Year helpers
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;

class FinancialYearService
{
    public static function getActive(): ?array
    {
        $db = Database::getInstance();
        return $db->fetch('SELECT * FROM financial_years WHERE is_active = 1 LIMIT 1');
    }

    public static function getById(int $id): ?array
    {
        return Database::getInstance()->fetch(
            'SELECT * FROM financial_years WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Resolve FY for a given voucher date.
     * Creates FY row if missing (July–June by default).
     */
    public static function resolveForDate(string $date): array
    {
        $db = Database::getInstance();
        $existing = $db->fetch(
            'SELECT * FROM financial_years WHERE :d BETWEEN start_date AND end_date LIMIT 1',
            ['d' => $date]
        );
        if ($existing) {
            return $existing;
        }

        // Auto-create FY based on FY_START_MONTH
        $dt = new DateTimeImmutable($date);
        $year = (int)$dt->format('Y');
        $month = (int)$dt->format('n');
        $startMonth = (int)(Database::getInstance()->fetchColumn(
            "SELECT setting_value FROM settings WHERE setting_key = 'fy_start_month'"
        ) ?: FY_START_MONTH);

        if ($startMonth === 1) {
            $start = sprintf('%04d-01-01', $year);
            $end   = sprintf('%04d-12-31', $year);
            $code  = (string)$year;
            $title = 'FY ' . $year;
        } else {
            // e.g. July–June
            if ($month >= $startMonth) {
                $y1 = $year;
                $y2 = $year + 1;
            } else {
                $y1 = $year - 1;
                $y2 = $year;
            }
            $start = sprintf('%04d-%02d-01', $y1, $startMonth);
            $endDt = (new DateTimeImmutable($start))->modify('+1 year -1 day');
            $end   = $endDt->format('Y-m-d');
            $code  = $y1 . '-' . $y2;
            $title = 'FY ' . $code;
        }

        $db->query(
            'INSERT INTO financial_years (code, title, start_date, end_date, is_active)
             VALUES (:c, :t, :s, :e, 0)',
            ['c' => $code, 't' => $title, 's' => $start, 'e' => $end]
        );

        return $db->fetch('SELECT * FROM financial_years WHERE id = :id', [
            'id' => (int)$db->lastInsertId(),
        ]);
    }

    public static function listAll(): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT * FROM financial_years ORDER BY start_date DESC'
        );
    }
}
