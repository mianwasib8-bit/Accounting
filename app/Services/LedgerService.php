<?php
/**
 * Ledger balance & posting helpers
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class LedgerService
{
    /**
     * Current balance of a subsidiary account.
     * Formula: Opening (signed by nature) + sum(debits) - sum(credits) for Dr accounts
     *          Opening + sum(credits) - sum(debits) for Cr accounts
     * Returns signed balance in account nature terms (positive = normal side).
     */
    public static function previousBalance(int $subsidiaryId): float
    {
        $db = Database::getInstance();
        $acc = $db->fetch(
            'SELECT opening_balance, nature FROM subsidiary_heads WHERE id = :id',
            ['id' => $subsidiaryId]
        );
        if (!$acc) {
            return 0.0;
        }

        $sums = $db->fetch(
            'SELECT COALESCE(SUM(debit),0) AS dr, COALESCE(SUM(credit),0) AS cr
             FROM ledger WHERE subsidiary_id = :id',
            ['id' => $subsidiaryId]
        );

        $opening = (float)$acc['opening_balance'];
        $dr = (float)$sums['dr'];
        $cr = (float)$sums['cr'];

        if ($acc['nature'] === 'Dr') {
            return round($opening + $dr - $cr, 2);
        }
        return round($opening + $cr - $dr, 2);
    }

    /**
     * Post double-entry lines to ledger. Caller must be inside a transaction.
     *
     * @param array $lines [ ['subsidiary_id'=>, 'narration'=>, 'debit'=>, 'credit'=>], ... ]
     */
    public static function post(
        int $fyId,
        int $sequenceNo,
        string $voucherType,
        int $voucherId,
        string $voucherRef,
        string $voucherDate,
        array $lines,
        ?int $userId
    ): void {
        $db = Database::getInstance();
        $stmt = $db->pdo()->prepare(
            'INSERT INTO ledger
               (financial_year_id, sequence_no, voucher_type, voucher_id, voucher_ref,
                voucher_date, subsidiary_id, narration, debit, credit, balance_after, created_by)
             VALUES
               (:fy, :seq, :vt, :vid, :ref, :vd, :sid, :narr, :dr, :cr, :bal, :uid)'
        );

        foreach ($lines as $line) {
            $sid = (int)$line['subsidiary_id'];
            $dr  = round((float)($line['debit'] ?? 0), 2);
            $cr  = round((float)($line['credit'] ?? 0), 2);
            $bal = self::previousBalance($sid);
            // After this line
            $acc = $db->fetch('SELECT nature FROM subsidiary_heads WHERE id = :id', ['id' => $sid]);
            if ($acc && $acc['nature'] === 'Dr') {
                $bal = round($bal + $dr - $cr, 2);
            } else {
                $bal = round($bal + $cr - $dr, 2);
            }

            $stmt->execute([
                'fy'   => $fyId,
                'seq'  => $sequenceNo,
                'vt'   => $voucherType,
                'vid'  => $voucherId,
                'ref'  => $voucherRef,
                'vd'   => $voucherDate,
                'sid'  => $sid,
                'narr' => $line['narration'] ?? null,
                'dr'   => $dr,
                'cr'   => $cr,
                'bal'  => $bal,
                'uid'  => $userId,
            ]);
        }
    }

    /** Cash balance (sum of all cash/bank type accounts) */
    public static function totalCashBalance(): float
    {
        $db = Database::getInstance();
        $accounts = $db->fetchAll(
            "SELECT id FROM subsidiary_heads WHERE account_type IN ('cash','bank') AND is_active = 1"
        );
        $total = 0.0;
        foreach ($accounts as $a) {
            $total += self::previousBalance((int)$a['id']);
        }
        return round($total, 2);
    }
}
