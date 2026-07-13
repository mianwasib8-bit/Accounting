<?php
/**
 * Cash Receipt / Cash Payment / Journal Voucher
 * - CRV/CPV: amount-only lines (min 2) — cash usually row 2
 * - JV: debit/credit lines, Total Dr must equal Total Cr
 * - Voucher no 1,2,3… per type per FY
 * - sequence_no SHARED across CRV + CPV + JV within FY
 */

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use RuntimeException;

class VoucherService
{
    public static function previewNext(string $type, string $date): array
    {
        $type = strtoupper($type);
        if (!in_array($type, ['CRV', 'CPV', 'JV'], true)) {
            throw new RuntimeException('Invalid voucher type.');
        }
        $fy = FinancialYearService::resolveForDate($date);
        $db = Database::getInstance();

        $nextNo = (int)$db->fetchColumn(
            'SELECT COALESCE(MAX(voucher_no),0)+1 FROM voucher_sequence
             WHERE financial_year_id = :fy AND voucher_type = :t',
            ['fy' => $fy['id'], 't' => $type]
        );
        $nextSeq = (int)$db->fetchColumn(
            'SELECT COALESCE(MAX(sequence_no),0)+1 FROM voucher_sequence
             WHERE financial_year_id = :fy',
            ['fy' => $fy['id']]
        );

        return [
            'voucher_no'  => $nextNo,
            'voucher_ref' => (string)$nextNo,
            'sequence_no' => $nextSeq,
            'fy'          => $fy,
        ];
    }

    public static function saveCashReceipt(array $data): array
    {
        return self::saveCashVoucher('CRV', $data);
    }

    public static function saveCashPayment(array $data): array
    {
        return self::saveCashVoucher('CPV', $data);
    }

    /**
     * Journal Voucher — pure double-entry.
     * lines: [{subsidiary_id, narration, debit_amount, credit_amount}, ...]
     * Min 2 lines; Total Debit === Total Credit; shared sequence_no with CRV/CPV.
     */
    public static function saveJournal(array $data): array
    {
        $date = trim((string)($data['voucher_date'] ?? ''));
        $lines = $data['lines'] ?? [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Invalid voucher date.');
        }
        if (!is_array($lines) || count($lines) < 2) {
            throw new RuntimeException('At least two journal lines are required.');
        }

        $db = Database::getInstance();
        $userId = Auth::id();

        $clean = [];
        $totalDr = 0.0;
        $totalCr = 0.0;

        foreach ($lines as $line) {
            $sid = (int)($line['subsidiary_id'] ?? 0);
            $dr  = round((float)($line['debit_amount'] ?? 0), 2);
            $cr  = round((float)($line['credit_amount'] ?? 0), 2);
            $narr = trim((string)($line['narration'] ?? ''));

            if ($sid <= 0) {
                continue;
            }
            if ($dr < 0 || $cr < 0) {
                throw new RuntimeException('Amounts cannot be negative.');
            }
            if ($dr > 0 && $cr > 0) {
                throw new RuntimeException('A line cannot have both debit and credit.');
            }
            if ($dr == 0.0 && $cr == 0.0) {
                continue;
            }

            $acc = $db->fetch(
                'SELECT id FROM subsidiary_heads WHERE id = :id AND is_active = 1',
                ['id' => $sid]
            );
            if (!$acc) {
                throw new RuntimeException('Invalid account on a line.');
            }

            $clean[] = [
                'subsidiary_id'    => $sid,
                'narration'        => $narr !== '' ? mb_substr($narr, 0, 500) : null,
                'debit_amount'     => $dr,
                'credit_amount'    => $cr,
                'previous_balance' => LedgerService::previousBalance($sid),
                'line_no'          => count($clean) + 1,
            ];
            $totalDr += $dr;
            $totalCr += $cr;
        }

        if (count($clean) < 2) {
            throw new RuntimeException('Enter at least two lines with amounts.');
        }
        if (abs($totalDr - $totalCr) > 0.009) {
            throw new RuntimeException(sprintf(
                'Total Debit (%.2f) must equal Total Credit (%.2f).',
                $totalDr,
                $totalCr
            ));
        }
        if ($totalDr <= 0) {
            throw new RuntimeException('Journal total must be greater than zero.');
        }

        $headerNarr = null;
        foreach ($clean as $line) {
            if (!empty($line['narration'])) {
                $headerNarr = $line['narration'];
                break;
            }
        }

        $fy = FinancialYearService::resolveForDate($date);

        try {
            $db->beginTransaction();

            // Lock shared sequence for entire FY (CRV/CPV/JV)
            $db->query(
                'SELECT id FROM voucher_sequence WHERE financial_year_id = :fy FOR UPDATE',
                ['fy' => $fy['id']]
            );

            $voucherNo = (int)$db->fetchColumn(
                'SELECT COALESCE(MAX(voucher_no),0)+1 FROM voucher_sequence
                 WHERE financial_year_id = :fy AND voucher_type = :t',
                ['fy' => $fy['id'], 't' => 'JV']
            );
            $sequenceNo = (int)$db->fetchColumn(
                'SELECT COALESCE(MAX(sequence_no),0)+1 FROM voucher_sequence
                 WHERE financial_year_id = :fy',
                ['fy' => $fy['id']]
            );
            $voucherRef = (string)$voucherNo;

            $db->query(
                'INSERT INTO journal_vouchers
                   (financial_year_id, voucher_no, voucher_ref, voucher_date,
                    total_debit, total_credit, narration, created_by)
                 VALUES
                   (:fy, :no, :ref, :dt, :tdr, :tcr, :narr, :uid)',
                [
                    'fy' => $fy['id'],
                    'no' => $voucherNo,
                    'ref' => $voucherRef,
                    'dt' => $date,
                    'tdr' => $totalDr,
                    'tcr' => $totalCr,
                    'narr' => $headerNarr,
                    'uid' => $userId,
                ]
            );
            $voucherId = (int)$db->lastInsertId();

            $ins = $db->pdo()->prepare(
                'INSERT INTO journal_voucher_details
                   (voucher_id, line_no, subsidiary_id, narration, debit_amount, credit_amount, previous_balance)
                 VALUES
                   (:vid, :ln, :sid, :narr, :dr, :cr, :pb)'
            );
            foreach ($clean as $line) {
                $ins->execute([
                    'vid'  => $voucherId,
                    'ln'   => $line['line_no'],
                    'sid'  => $line['subsidiary_id'],
                    'narr' => $line['narration'],
                    'dr'   => $line['debit_amount'],
                    'cr'   => $line['credit_amount'],
                    'pb'   => $line['previous_balance'],
                ]);
            }

            $db->query(
                'INSERT INTO voucher_sequence
                   (financial_year_id, sequence_no, voucher_type, voucher_id, voucher_no, voucher_ref, voucher_date)
                 VALUES
                   (:fy, :seq, :vt, :vid, :vno, :ref, :dt)',
                [
                    'fy'  => $fy['id'],
                    'seq' => $sequenceNo,
                    'vt'  => 'JV',
                    'vid' => $voucherId,
                    'vno' => $voucherNo,
                    'ref' => $voucherRef,
                    'dt'  => $date,
                ]
            );

            $ledgerLines = [];
            foreach ($clean as $line) {
                $ledgerLines[] = [
                    'subsidiary_id' => $line['subsidiary_id'],
                    'narration'     => $line['narration'] ?: $headerNarr,
                    'debit'         => $line['debit_amount'],
                    'credit'        => $line['credit_amount'],
                ];
            }

            LedgerService::post(
                (int)$fy['id'],
                $sequenceNo,
                'JV',
                $voucherId,
                $voucherRef,
                $date,
                $ledgerLines,
                $userId
            );

            $db->commit();

            Audit::log(
                $userId,
                'create',
                'jv',
                $voucherRef,
                "Posted JV #{$voucherNo} amount {$totalDr}"
            );

            return [
                'voucher_id'   => $voucherId,
                'voucher_no'   => $voucherNo,
                'voucher_ref'  => $voucherRef,
                'sequence_no'  => $sequenceNo,
                'total'        => $totalDr,
                'total_debit'  => $totalDr,
                'total_credit' => $totalCr,
                'fy_code'      => $fy['code'],
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function saveCashVoucher(string $type, array $data): array
    {
        $date = trim((string)($data['voucher_date'] ?? ''));
        $lines = $data['lines'] ?? [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('Invalid voucher date.');
        }
        if (!is_array($lines) || count($lines) < 2) {
            throw new RuntimeException('At least two lines are required (e.g. party + cash).');
        }

        $db = Database::getInstance();
        $userId = Auth::id();

        $clean = [];
        foreach ($lines as $line) {
            $sid = (int)($line['subsidiary_id'] ?? 0);
            $amt = round((float)($line['amount'] ?? 0), 2);
            $narr = trim((string)($line['narration'] ?? ''));
            if ($sid <= 0 || $amt <= 0) {
                continue;
            }
            $acc = $db->fetch(
                'SELECT id, account_type, title FROM subsidiary_heads WHERE id = :id AND is_active = 1',
                ['id' => $sid]
            );
            if (!$acc) {
                throw new RuntimeException('Invalid account on a line.');
            }
            $clean[] = [
                'subsidiary_id'    => $sid,
                'narration'        => $narr !== '' ? mb_substr($narr, 0, 500) : null,
                'amount'           => $amt,
                'account_type'     => $acc['account_type'],
                'previous_balance' => LedgerService::previousBalance($sid),
                'line_no'          => count($clean) + 1,
            ];
        }

        if (count($clean) < 2) {
            throw new RuntimeException('Enter at least two lines with amounts.');
        }

        // Split cash/bank vs others
        $cashLines = [];
        $otherLines = [];
        foreach ($clean as $line) {
            if (in_array($line['account_type'], ['cash', 'bank'], true)) {
                $cashLines[] = $line;
            } else {
                $otherLines[] = $line;
            }
        }

        // If user didn't mark cash type, treat last line as counter account (row 2 convention)
        if (!$cashLines && count($clean) >= 2) {
            $cashLines = [end($clean)];
            $otherLines = array_slice($clean, 0, -1);
        }
        if (!$otherLines) {
            throw new RuntimeException('Add a party/expense line and a cash/bank line.');
        }
        if (!$cashLines) {
            throw new RuntimeException('Add a cash/bank account on a line (usually row 2).');
        }

        $cashTotal = array_sum(array_column($cashLines, 'amount'));
        $otherTotal = array_sum(array_column($otherLines, 'amount'));
        if (abs($cashTotal - $otherTotal) > 0.009) {
            throw new RuntimeException(sprintf(
                'Cash side (%.2f) must equal other side (%.2f).',
                $cashTotal,
                $otherTotal
            ));
        }
        $total = $otherTotal;
        if ($total <= 0) {
            throw new RuntimeException('Total must be greater than zero.');
        }

        // Primary cash account for master FK
        $cashId = (int)$cashLines[0]['subsidiary_id'];

        $headerNarr = null;
        foreach ($clean as $line) {
            if (!empty($line['narration'])) {
                $headerNarr = $line['narration'];
                break;
            }
        }

        $fy = FinancialYearService::resolveForDate($date);

        try {
            $db->beginTransaction();

            $db->query(
                'SELECT id FROM voucher_sequence WHERE financial_year_id = :fy FOR UPDATE',
                ['fy' => $fy['id']]
            );

            $voucherNo = (int)$db->fetchColumn(
                'SELECT COALESCE(MAX(voucher_no),0)+1 FROM voucher_sequence
                 WHERE financial_year_id = :fy AND voucher_type = :t',
                ['fy' => $fy['id'], 't' => $type]
            );
            $sequenceNo = (int)$db->fetchColumn(
                'SELECT COALESCE(MAX(sequence_no),0)+1 FROM voucher_sequence
                 WHERE financial_year_id = :fy',
                ['fy' => $fy['id']]
            );
            $voucherRef = (string)$voucherNo;

            $table = $type === 'CRV' ? 'cash_receipt_vouchers' : 'cash_payment_vouchers';
            $detailTable = $type === 'CRV' ? 'cash_receipt_voucher_details' : 'cash_payment_voucher_details';

            $db->query(
                "INSERT INTO {$table}
                   (financial_year_id, voucher_no, voucher_ref, voucher_date,
                    cash_account_id, total_amount, narration, created_by)
                 VALUES
                   (:fy, :no, :ref, :dt, :cash, :tot, :narr, :uid)",
                [
                    'fy' => $fy['id'], 'no' => $voucherNo, 'ref' => $voucherRef,
                    'dt' => $date, 'cash' => $cashId, 'tot' => $total,
                    'narr' => $headerNarr, 'uid' => $userId,
                ]
            );
            $voucherId = (int)$db->lastInsertId();

            $ins = $db->pdo()->prepare(
                "INSERT INTO {$detailTable}
                   (voucher_id, line_no, subsidiary_id, narration, amount, previous_balance)
                 VALUES (:vid, :ln, :sid, :narr, :amt, :pb)"
            );
            $ln = 1;
            foreach ($otherLines as $line) {
                $ins->execute([
                    'vid' => $voucherId,
                    'ln'  => $ln++,
                    'sid' => $line['subsidiary_id'],
                    'narr'=> $line['narration'],
                    'amt' => $line['amount'],
                    'pb'  => $line['previous_balance'],
                ]);
            }

            // Ledger double-entry
            $ledgerLines = [];
            if ($type === 'CRV') {
                // Cash Dr, Others Cr
                foreach ($cashLines as $line) {
                    $ledgerLines[] = [
                        'subsidiary_id' => $line['subsidiary_id'],
                        'narration'     => $line['narration'] ?: $headerNarr,
                        'debit'         => $line['amount'],
                        'credit'        => 0,
                    ];
                }
                foreach ($otherLines as $line) {
                    $ledgerLines[] = [
                        'subsidiary_id' => $line['subsidiary_id'],
                        'narration'     => $line['narration'] ?: $headerNarr,
                        'debit'         => 0,
                        'credit'        => $line['amount'],
                    ];
                }
            } else {
                // Others Dr, Cash Cr
                foreach ($otherLines as $line) {
                    $ledgerLines[] = [
                        'subsidiary_id' => $line['subsidiary_id'],
                        'narration'     => $line['narration'] ?: $headerNarr,
                        'debit'         => $line['amount'],
                        'credit'        => 0,
                    ];
                }
                foreach ($cashLines as $line) {
                    $ledgerLines[] = [
                        'subsidiary_id' => $line['subsidiary_id'],
                        'narration'     => $line['narration'] ?: $headerNarr,
                        'debit'         => 0,
                        'credit'        => $line['amount'],
                    ];
                }
            }

            $db->query(
                'INSERT INTO voucher_sequence
                   (financial_year_id, sequence_no, voucher_type, voucher_id, voucher_no, voucher_ref, voucher_date)
                 VALUES
                   (:fy, :seq, :vt, :vid, :vno, :ref, :dt)',
                [
                    'fy' => $fy['id'],
                    'seq'=> $sequenceNo,
                    'vt' => $type,
                    'vid'=> $voucherId,
                    'vno'=> $voucherNo,
                    'ref'=> $voucherRef,
                    'dt' => $date,
                ]
            );

            LedgerService::post(
                (int)$fy['id'],
                $sequenceNo,
                $type,
                $voucherId,
                $voucherRef,
                $date,
                $ledgerLines,
                $userId
            );

            $db->commit();

            Audit::log(
                $userId,
                'create',
                strtolower($type),
                $voucherRef,
                "Posted {$type} #{$voucherNo} amount {$total}"
            );

            return [
                'voucher_id'  => $voucherId,
                'voucher_no'  => $voucherNo,
                'voucher_ref' => $voucherRef,
                'sequence_no' => $sequenceNo,
                'total'       => $total,
                'fy_code'     => $fy['code'],
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function listRecent(int $limit = 20): array
    {
        return Database::getInstance()->fetchAll(
            'SELECT vs.*, fy.code AS fy_code
             FROM voucher_sequence vs
             INNER JOIN financial_years fy ON fy.id = vs.financial_year_id
             ORDER BY vs.created_at DESC
             LIMIT ' . (int)$limit
        );
    }

    public static function countAll(): int
    {
        return (int)Database::getInstance()->fetchColumn('SELECT COUNT(*) FROM voucher_sequence');
    }
}
